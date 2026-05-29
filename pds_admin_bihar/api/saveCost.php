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
		$log_query = "select * from optimised_table WHERE id='$id'";
		$log_result = mysqli_query($con,$log_query);
		if ($log_result && $row = $log_result->fetch_assoc()) {
			$user_id =  $row['year'];
			$user_id1 =  $row['month'];
		}

        // First, validate if the value is a valid float or integer
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);
        
        // Check if the value is negative or not a valid number
        if ($value === false || $value < 0) {
            // If it's invalid or negative, skip this iteration
            echo "Error: Invalid or negative value: $value_temp<br>";
            return;
        }

        // At this point, we are certain that $value is a non-negative valid float or integer
        $value = number_format($value, 2, '.', ''); // Ensures it's in float format if necessary

        // Update the optimised table where id equals the extracted ID
        $sql = "UPDATE optimised_table SET cost = '$value' WHERE id = '$id'";
		$filteredPost = $_POST;
		unset($filteredPost['username'], $filteredPost['password']);
		writeLog("User ->" ." Cost for leg2 Added ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost). " | " . $user_id." | " . $user_id1);

        if ($con->query($sql) === TRUE) {
            // You may want to perform some action after the update, e.g., logging or confirmation message
        } else {
            echo "Error : updating record: " . $con->error;
            return;
        }
    }
}

// Redirect after the loop ends
echo "<script>window.location.href = '../Performa.php';</script>";
?>

<?php require('Fullui.php'); ?>
