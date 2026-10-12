
<?php
require_once "database.php";

$selectedLga = "";
$results = null;
$error = "";

// Get all LGAs in Delta State
$lgas = $conn->query(
    "SELECT lga_id, lga_name
     FROM lga
     WHERE state_id = 25
     ORDER BY lga_name"
);

// Check whether an LGA was selected
if (isset($_GET["lga_id"]) && $_GET["lga_id"] !== "") {
    $selectedLga = $_GET["lga_id"];

    if (filter_var($selectedLga, FILTER_VALIDATE_INT) === false) {
        $error = "Invalid LGA selected.";
    } else {
        // Add together each party's scores across the selected LGA
        $stmt = $conn->prepare(
            "SELECT apr.party_abbreviation,
                    SUM(apr.party_score) AS total_score
             FROM announced_pu_results apr
             INNER JOIN polling_unit pu
                 ON apr.polling_unit_uniqueid = pu.uniqueid
             WHERE pu.lga_id = ?
             GROUP BY apr.party_abbreviation
             ORDER BY total_score DESC"
        );

        $stmt->bind_param("i", $selectedLga);
        $stmt->execute();

        $results = $stmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGA Election Results</title>

  
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

    <h1>LGA Election Results</h1>

    <form method="GET">
        <label for="lga_id">Select Local Government Area:</label>

        <select name="lga_id" id="lga_id" required>
            <option value="">-- Choose LGA --</option>

            <?php while ($lga = $lgas->fetch_assoc()) { ?>
                <option
                    value="<?php echo $lga['lga_id']; ?>"
                    <?php
                    if ($selectedLga == $lga['lga_id']) {
                        echo "selected";
                    }
                    ?>
                >
                    <?php echo htmlspecialchars($lga['lga_name']); ?>
                </option>
            <?php } ?>
        </select>

        <button type="submit">View Total Results</button>
    </form>

    <?php if ($error !== "") { ?>
        <p><?php echo htmlspecialchars($error); ?></p>

    <?php } elseif ($results !== null) { ?>

        <h2>Total Party Scores</h2>

        <?php if ($results->num_rows > 0) { ?>

            <table border="1" cellpadding="8">
                <tr>
                    <th>Party</th>
                    <th>Total Score</th>
                </tr>

                <?php while ($row = $results->fetch_assoc()) { ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($row['party_abbreviation']); ?>
                        </td>
                        <td>
                            <?php echo number_format($row['total_score']); ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>

        <?php } else { ?>
            <p>No polling-unit results were found for this LGA.</p>
        <?php } ?>

    <?php } ?>

</body>
</html>
