<?php

// Function to sanitize and escape HTML characters
function escapeHTML($input) {
    return htmlspecialchars(strip_tags($input, '<b><i><u><strong><em><ul><ol><li>'), ENT_QUOTES, 'UTF-8');
}

/*function whitelistInput($input) {
    // Define a whitelist of allowed characters for alphanumeric and spaces
	$allowedCharacters = "/^[a-zA-Z0-9@\.\s\-\_\#\$]+$/";

    // Check if the input matches the alphanumeric and spaces whitelist
    return preg_match($allowedCharacters, $input) ? $input : false;
}*/

function whitelistInput($input) {
    // Define a whitelist of allowed characters for alphanumeric and spaces
	$allowedCharacters = "/[^a-zA-Z0-9@\.\s\-_#\$]+/";
    
    // Remove disallowed characters from the input
    $sanitizedInput = preg_replace($allowedCharacters, "", $input);

    // Return the sanitized input
    return $sanitizedInput;;
}


function removeWhiteSpace($string){
	$clean_string = preg_replace('/\s+/u', ' ', $string);
	return $clean_string;
}

// Function for HTML and URL decoding followed by validation
function validateAndDecode($input) {
    // Decode HTML entities and URL encoding
    $decodedInput = urldecode(html_entity_decode($input, ENT_QUOTES, 'UTF-8'));
    
    // Perform additional validation based on your requirements
    // For example, you can check length, characters, format, etc.
    // Here, we are just checking if the input is not empty
    return !empty($decodedInput) ? $decodedInput : false;
}

// Apply positive input validation to all elements in $_POST
foreach ($_POST as $key => $value) {
    //$_POST[$key] = removeWhiteSpace($value);
}

// Apply positive input validation to all elements in $_GET
foreach ($_GET as $key => $value) {
    $_GET[$key] = removeWhiteSpace($value);
}

// Check and sanitize all elements in $_POST
foreach ($_POST as $key => $value) {
    //$_POST[$key] = escapeHTML($value);
}

// Check and sanitize all elements in $_GET
foreach ($_GET as $key => $value) {
    //$_GET[$key] = escapeHTML($value);
}

// Apply positive input validation to all elements in $_POST
foreach ($_POST as $key => $value) {
    $_POST[$key] = whitelistInput($value);
}

// Apply positive input validation to all elements in $_GET
foreach ($_GET as $key => $value) {
    $_GET[$key] = whitelistInput($value);
}

// Apply HTML and URL decoding followed by validation to all elements in $_POST
foreach ($_POST as $key => $value) {
   $_POST[$key] = validateAndDecode($value);
}

// Apply HTML and URL decoding followed by validation to all elements in $_GET
foreach ($_GET as $key => $value) {
    $_GET[$key] = validateAndDecode($value);
}

?>
