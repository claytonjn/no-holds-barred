<?php
    header('Content-type: application/json');

    //**This include file establishes the database connections
	include_once ("./includes/db_connect.inc");

	//**This include file includes Sierra API functions
	include_once ("./includes/functions.php");

	$token = getAccessToken(); //Token for Sierra API access (get new token for each bib, otherwise it might timeout)
    
    //Serialize and validate input data
    $patronId = filter_var($_POST['patronId'], FILTER_VALIDATE_INT);
    $holdId = filter_var($_POST['holdId'], FILTER_VALIDATE_INT);
    
    //Get hold information to verify that the hold being called is for the correct patron
    //this is to prevent random hold IDs from being sent through this script and deleted
	$holdResult = getHold($token, $holdId);
	$holdResult = json_decode($holdResult, true);
    
    //Set default assistance text, assuming cancelling did not work due to API error or ID mismatch
    $result = " For assistance, please contact the Circulation department.<br>
                <b>Email:</b> email@domain.org<br>
                <b>Phone:</b> (123) 456-7890";
    
    if (isset($holdResult['patron'])) {
        $patronWithHold = substr($holdResult['patron'],-7);
        
        //Make sure that expected patron ID was returned
        if($patronId == $patronWithHold) {			
			//Delete the hold
			$deleteResult = deleteHold($token, $holdId);
			if($deleteResult == NULL) {
				$result = "Your hold on this item has been successfully canceled";
			} else {
                $result = "There was a problem cancelling this hold.<br><br>" . $result;
            }
        } else {
            $result = "There was a problem cancelling this hold.<br><br>" . $result;
        }
	} else {
        $result = "This hold has already been cancelled.<br><br>" . $result;
    }

    //Build array to send back
    $array['result'] = $result;
    
    //Send back json encoded array
    echo json_encode($array);
?>