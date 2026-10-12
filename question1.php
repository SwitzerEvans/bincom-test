
<?php
require_once "database.php";

$selectedUnit = "";
$results = null;
$error = "";

// Get the selected polling unit from the URL
if (isset($_GET["unit_id"])) {
    $selectedUnit = $_GET["unit_id"];

    if (filter_var($selectedUnit, FILTER_VALIDATE_INT) === false) {
        $error = "Invalid polling unit selected.";
    } else {
        // Get the party results for this polling unit
        $stmt = $conn->prepare(
            "SELECT party_abbreviation, party_score
             FROM announced_pu_results
             WHERE polling_unit_uniqueid = ?
             ORDER BY result_id"
        );

        $stmt->bind_param("s", $selectedUnit);
        $stmt->execute();

        $results = $stmt->get_result();
    }
}

// Get polling units belonging to Delta State
$units = $conn->query(
    "SELECT polling_unit.uniqueid,
            polling_unit.polling_unit_number,
            polling_unit.polling_unit_name
     FROM polling_unit
     INNER JOIN lga
         ON polling_unit.lga_id = lga.lga_id
     WHERE lga.state_id = 25
     ORDER BY polling_unit.uniqueid"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polling Unit Results</title>
   
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
            background-color: #ffffff;
            max-width: 600px;
            margin: 0 auto 30px;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        select {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background-color: #fff;
            font-size: 15px;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #1e3a5f;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background-color: #294f7d;
        }

        h2 {
            max-width: 600px;
            margin: 30px auto 15px;
            color: #1e3a5f;
        }

        table {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        th,
        td {
            padding: 14px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #1e3a5f;
            color: #fff;
        }

        tr:nth-child(even) {
            background-color: #f2f5f9;
        }

        body > p {
            max-width: 600px;
            margin: 20px auto;
            padding: 15px;
            background-color: #fff;
            border-radius: 6px;
        }

        @media (max-width: 600px) {
            body {
                padding: 25px 12px;
            }

            form {
                padding: 18px;
            }

            th,
            td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>

    <h1>Polling Unit Results</h1>

    <form method="GET">
        <label for="unit_id">Select a polling unit:</label>

        <select name="unit_id" id="unit_id" required>
            <option value="">-- Choose polling unit --</option>

            <?php while ($unit = $units->fetch_assoc()) { ?>
                <option
                    value="<?php echo $unit['uniqueid']; ?>"
                    <?php
                    if ($selectedUnit == $unit['uniqueid']) {
                        echo "selected";
                    }
                    ?>
                >
                    <?php
                    echo htmlspecialchars(
                        $unit['polling_unit_number'] . " - " .
                        $unit['polling_unit_name']
                    );
                    ?>
                </option>
            <?php } ?>
        </select>

        <button type="submit">View Results</button>
    </form>

    <?php if ($error !== "") { ?>

        <p><?php echo htmlspecialchars($error); ?></p>

    <?php } elseif ($results !== null) { ?>

        <h2>Selected Polling Unit Results</h2>

        <?php if ($results->num_rows > 0) { ?>

            <table border="1" cellpadding="8">
                <tr>
                    <th>Party</th>
                    <th>Score</th>
                </tr>

                <?php while ($row = $results->fetch_assoc()) { ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($row['party_abbreviation']); ?>
                        </td>
                        <td>
                            <?php echo $row['party_score']; ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>

        <?php } else { ?>
            <p>No results have been recorded for this polling unit.</p>
        <?php } ?>

    <?php } ?>

</body>
</html>
