<?php

require('util/Connection.php');

 ?>

<script>

var x = document.getElementById("district");

<?php
$query = "SELECT * FROM districts";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
while($row = mysqli_fetch_array($result)){
	echo 'var option = document.createElement("option");';
	echo 'option.text = "'.$row['name'].'";';
	echo 'option.value = "'.$row['name'].'";';
	echo 'x.add(option);';
}

?>
</script>