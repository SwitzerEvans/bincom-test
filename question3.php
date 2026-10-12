
<?php
require_once "database.php";

$message = "";
$error = "";

// Keep form values after submitting
$lgaId = $_POST["lga_id"] ?? "";

// Clear the ward when the Load Wards button is clicked
$wardId = isset($_POST["load_wards"])
    ? ""
    : ($_POST["ward_uniqueid"] ?? "");

$unitNumber = trim($_POST["unit_number"] ?? "");
$unitName = trim($_POST["unit_name"] ?? "");
$description = trim($_POST["description"] ?? "");
$scores = $_POST["scores"] ?? [];

// Get all Delta State LGAs
$lgas = $conn->query(
    "SELECT lga_id, lga_name
     FROM lga
     WHERE state_id = 25
     ORDER BY lga_name"
);

// Load wards belonging to the selected LGA
$wards = null;

if (
    $lgaId !== "" &&
    filter_var($lgaId, FILTER_VALIDATE_INT) !== false
) {
    $wardQuery = $conn->prepare(
        "SELECT uniqueid, ward_id, ward_name
         FROM ward
         WHERE lga_id = ?
         ORDER BY ward_name"
    );

    if ($wardQuery === false) {
        die("Could not load wards: " . $conn->error);
    }

    $wardQuery->bind_param("i", $lgaId);
    $wardQuery->execute();
    $wards = $wardQuery->get_result();
    $wardQuery->close();
}

// Get all political parties
$parties = [];

$partyQuery = $conn->query(
    "SELECT partyid, partyname
     FROM party
     ORDER BY partyid"
);

while ($party = $partyQuery->fetch_assoc()) {
    $parties[] = $party;
}

$partyQuery->free();

// Process the form only when Save is clicked
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_results"])
) {
    $validLga = filter_var($lgaId, FILTER_VALIDATE_INT);
    $validWard = filter_var($wardId, FILTER_VALIDATE_INT);

    // Validate the basic polling unit details
    if (
        $validLga === false ||
        $validLga <= 0 ||
        $validWard === false ||
        $validWard <= 0 ||
        $unitNumber === "" ||
        $unitName === ""
    ) {
        $error = "Please complete all required polling unit details.";
    }

    // Confirm the LGA belongs to Delta State
    if ($error === "") {
        $checkLga = $conn->prepare(
            "SELECT lga_id
             FROM lga
             WHERE lga_id = ? AND state_id = 25"
        );

        if ($checkLga === false) {
            die("LGA query error: " . $conn->error);
        }

        $checkLga->bind_param("i", $validLga);
        $checkLga->execute();
        $checkLga->store_result();

        $lgaExists = $checkLga->num_rows > 0;

        $checkLga->close();

        if (!$lgaExists) {
            $error = "Invalid LGA selected.";
        }
    }

    // Confirm the ward belongs to the selected LGA
    $ward = null;

    if ($error === "") {
        $checkWard = $conn->prepare(
            "SELECT uniqueid, ward_id
             FROM ward
             WHERE uniqueid = ? AND lga_id = ?"
        );

        if ($checkWard === false) {
            die("Ward query error: " . $conn->error);
        }

        $checkWard->bind_param(
            "ii",
            $validWard,
            $validLga
        );

        $checkWard->execute();

        $wardResult = $checkWard->get_result();

        if ($wardResult === false) {
            die("Could not retrieve ward: " . $checkWard->error);
        }

        $ward = $wardResult->fetch_assoc();

        $wardResult->free();
        $checkWard->close();

        if (!$ward) {
            $error = "Please select a valid ward for this LGA.";
        }
    }

    // Validate a score for every party
    $cleanScores = [];

    if ($error === "") {
        if (!is_array($scores) || count($parties) === 0) {
            $error = "No party scores were submitted.";
        } else {
            foreach ($parties as $party) {
                $partyId = $party["partyid"];
                $rawScore = $scores[$partyId] ?? null;

                if (
                    !is_scalar($rawScore) ||
                    $rawScore === "" ||
                    filter_var(
                        (string) $rawScore,
                        FILTER_VALIDATE_INT
                    ) === false ||
                    (int) $rawScore < 0
                ) {
                    $error =
                        "Enter a valid score of zero or more for every party.";
                    break;
                }

                $cleanScores[$partyId] = (int) $rawScore;
            }
        }
    }

    // Save the polling unit and party results
    if ($error === "") {
        $transactionStarted = false;

        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            // Generate the next polling_unit_id
            $idResult = $conn->query(
                "SELECT COALESCE(MAX(polling_unit_id), 0) + 1
                 AS next_id
                 FROM polling_unit"
            );

            $idRow = $idResult->fetch_assoc();
            $newPollingUnitId = (int) $idRow["next_id"];

            $idResult->free();

            $enteredBy = "Assessment Entry";
            $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "";

            // Insert the new polling unit
            $insertUnit = $conn->prepare(
                "INSERT INTO polling_unit (
                    polling_unit_id,
                    ward_id,
                    lga_id,
                    uniquewardid,
                    polling_unit_number,
                    polling_unit_name,
                    polling_unit_description,
                    entered_by_user,
                    date_entered,
                    user_ip_address
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
            );

            if ($insertUnit === false) {
                throw new RuntimeException(
                    "Polling unit query error: " . $conn->error
                );
            }

            $insertUnit->bind_param(
                "iiiisssss",
                $newPollingUnitId,
                $ward["ward_id"],
                $validLga,
                $ward["uniqueid"],
                $unitNumber,
                $unitName,
                $description,
                $enteredBy,
                $ipAddress
            );

            $insertUnit->execute();
            $insertUnit->close();

            // Get the automatically generated uniqueid
            $newUnitId = (string) $conn->insert_id;

            // Prepare to insert each party's result
            $insertResult = $conn->prepare(
                "INSERT INTO announced_pu_results (
                    polling_unit_uniqueid,
                    party_abbreviation,
                    party_score,
                    entered_by_user,
                    date_entered,
                    user_ip_address
                ) VALUES (?, ?, ?, ?, NOW(), ?)"
            );

            if ($insertResult === false) {
                throw new RuntimeException(
                    "Party results query error: " . $conn->error
                );
            }

            foreach ($cleanScores as $partyId => $score) {
                $insertResult->bind_param(
                    "ssiss",
                    $newUnitId,
                    $partyId,
                    $score,
                    $enteredBy,
                    $ipAddress
                );

                $insertResult->execute();
            }

            $insertResult->close();

            // Save everything together
            $conn->commit();
            $transactionStarted = false;

            $message =
                "Polling unit and all party results saved successfully. " .
                "New polling unit ID: " . $newUnitId;

            // Clear the form after successful saving
            $lgaId = "";
            $wardId = "";
            $unitNumber = "";
            $unitName = "";
            $description = "";
            $scores = [];
            $wards = null;

        } catch (Throwable $e) {
            if ($transactionStarted) {
                $conn->rollback();
            }

            $error = "Could not save the results: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>New Polling Unit Results</title>

        <style>
            * {
                box-sizing: border-box;
            }

            body {
                font-family: Arial, sans-serif;
                background-color: #f4f7fb;
                margin: 0;
                padding: 40px 20px;
                color: #333;
            }

            h1 {
                text-align: center;
                color: #1e3a5f;
                margin-bottom: 30px;
            }

            form {
                background-color: #fff;
                max-width: 750px;
                margin: 0 auto;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            }

            h2 {
                color: #1e3a5f;
                font-size: 20px;
                margin-top: 30px;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #e5eaf0;
            }

            h2:first-child {
                margin-top: 0;
            }

            label {
                display: block;
                margin-top: 18px;
                margin-bottom: 8px;
                font-weight: bold;
                color: #374151;
            }

            input,
            select,
            textarea {
                display: block;
                width: 100%;
                padding: 12px;
                border: 1px solid #ccd3dc;
                border-radius: 6px;
                background-color: #fff;
                font-family: Arial, sans-serif;
                font-size: 15px;
            }

            input:focus,
            select:focus,
            textarea:focus {
                outline: none;
                border-color: #1e3a5f;
                box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.1);
            }

            select:disabled {
                background-color: #edf0f4;
                color: #555;
                cursor: not-allowed;
            }

            textarea {
                resize: vertical;
                min-height: 90px;
            }

            button {
                display: block;
                width: 100%;
                margin-top: 20px;
                padding: 13px;
                background-color: #1e3a5f;
                color: #fff;
                border: none;
                border-radius: 6px;
                font-size: 15px;
                font-weight: bold;
                cursor: pointer;
            }

            button:hover {
                background-color: #294f7d;
            }

            p {
                line-height: 1.6;
            }

            .message,
            .error {
                max-width: 750px;
                margin: 0 auto 20px;
                padding: 15px;
                border-radius: 6px;
                line-height: 1.5;
            }

            .message {
                background-color: #d8f3dc;
                color: #215c35;
                border: 1px solid #a8dfb4;
            }

            .error {
                background-color: #ffe0e0;
                color: #922b2b;
                border: 1px solid #f0b4b4;
            }

            @media (max-width: 600px) {
                body {
                    padding: 25px 12px;
                }

                form {
                    padding: 20px 16px;
                }

                h1 {
                    font-size: 25px;
                }

                h2 {
                    font-size: 18px;
                }

                input,
                select,
                textarea {
                    font-size: 16px;
                }
            }
        </style>

</head>

<body>

    <h1>Register New Polling Unit Results</h1>

    <?php if ($message !== "") { ?>
        <p class="message">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php } ?>

    <?php if ($error !== "") { ?>
        <p class="error">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php } ?>

    <form method="POST">

        <h2>Location</h2>

        <label for="state">State</label>
        <select id="state" disabled>
            <option selected>Delta State</option>
        </select>

        <label for="lga_id">Local Government Area</label>

        <select name="lga_id" id="lga_id" required>
            <option value="">-- Select LGA --</option>

            <?php while ($lga = $lgas->fetch_assoc()) { ?>
                <option
                    value="<?php echo (int) $lga["lga_id"]; ?>"
                    <?php
                    if ((string) $lgaId === (string) $lga["lga_id"]) {
                        echo "selected";
                    }
                    ?>
                >
                    <?php echo htmlspecialchars($lga["lga_name"]); ?>
                </option>
            <?php } ?>
        </select>

        <button type="submit" name="load_wards" value="1" formnovalidate>
            Load Wards
        </button>

        <label for="ward_uniqueid">Ward</label>

        <select name="ward_uniqueid" id="ward_uniqueid" required>
            <option value="">-- Select Ward --</option>

            <?php if ($wards !== null) { ?>
                <?php while ($wardOption = $wards->fetch_assoc()) { ?>
                    <option
                        value="<?php echo (int) $wardOption["uniqueid"]; ?>"
                        <?php
                        if ((string) $wardId === (string) $wardOption["uniqueid"]) {
                            echo "selected";
                        }
                        ?>
                    >
                        <?php echo htmlspecialchars($wardOption["ward_name"]); ?>
                    </option>
                <?php } ?>
            <?php } ?>
        </select>

        <h2>Polling Unit Details</h2>

        <label for="unit_number">Polling Unit Number</label>
        <input
            type="text"
            name="unit_number"
            id="unit_number"
            value="<?php echo htmlspecialchars($unitNumber); ?>"
            required
        >

        <label for="unit_name">Polling Unit Name</label>
        <input
            type="text"
            name="unit_name"
            id="unit_name"
            value="<?php echo htmlspecialchars($unitName); ?>"
            required
        >

        <label for="description">Description</label>
        <textarea
            name="description"
            id="description"
            rows="3"
        ><?php echo htmlspecialchars($description); ?></textarea>

        <h2>Party Scores</h2>

        <p>Enter a whole-number score for every party. Use 0 if a party received no votes.</p>

        <?php foreach ($parties as $party) { ?>
            <label for="score_<?php echo htmlspecialchars($party["partyid"]); ?>">
                <?php
                echo htmlspecialchars(
                    $party["partyname"] . " (" . $party["partyid"] . ")"
                );
                ?>
            </label>

            <input
                type="number"
                id="score_<?php echo htmlspecialchars($party["partyid"]); ?>"
                name="scores[<?php echo htmlspecialchars($party["partyid"]); ?>]"
                min="0"
                step="1"
                value="<?php
                    echo htmlspecialchars(
                        (string) ($scores[$party["partyid"]] ?? "")
                    );
                ?>"
                required
            >
        <?php } ?>

        <button type="submit" name="save_results" value="1">
            Save Polling Unit and Results
        </button>

    </form>
   
<script>
    // to reset my select ward when i click new Local Government Area
    const lgaSelect = document.getElementById("lga_id");
    const wardSelect = document.getElementById("ward_uniqueid");

    lgaSelect.addEventListener("change", function () {
        wardSelect.innerHTML = '<option value="">-- Select Ward --</option>';
    });
</script>

</body>
</html>