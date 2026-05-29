<?php
require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../util/Logger.php');


if (!SessionCheck()) {
    return;
}

require('Header.php');

foreach ($_POST as $key => $value) {
    // Check if the parameter starts with 'cost_' and is not empty
    if (substr($key, 0, 5) === 'cost_' && !empty($value)) {
        // Extract the ID from the parameter name
        $id = substr($key, 5);
        $value_temp = $value;
		$log_query = "select * from optimised_table_leg1 WHERE id='$id'";
		$log_result = mysqli_query($con,$log_query);
		if ($log_result && $row = $log_result->fetch_assoc()) {
			$user_id =  $row['year'];
			$user_id1 =  $row['month'];
		}

        // First, validate if the value is a valid float
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);

        // If the value is invalid or negative, skip this iteration
        if ($value === false || $value < 0) {
            echo "Error: Invalid or negative value: $value_temp<br>";
            return;
        }

        // Ensure the value is formatted correctly as a float (optional step)
        $value = number_format($value, 2, '.', ''); // This ensures 2 decimal points for floats

        // Update the optimised table where id equals the extracted ID
        $sql = "UPDATE optimised_table_leg1 SET cost = '$value' WHERE id = '$id'";
		$filteredPost = $_POST;
		unset($filteredPost['username'], $filteredPost['password']);
		writeLog("User ->" ." Cost for legl Added ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost). " | " . $user_id." | " . $user_id1);

        if ($con->query($sql) === TRUE) {
            // Optionally, you could add logic here after a successful update
        } else {
            echo "Error: updating record: " . $con->error;
            return;
        }
    }
}

// Redirect after processing all the POST data
echo "<script>window.location.href = '../PerformaLeg1.php';</script>";
?>

<?php require('Fullui.php'); ?>
