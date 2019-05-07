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
    
    //Set default type and assistance text, assuming cancelling did not work due to API error or ID mismatch
    $type = "orange";
    $content = " For assistance, please contact the Circulation department.<br>
                <span style='display:inline-block; font-weight:bold; padding-left:2em; padding-right:1em;'><i class='fas fa-at'></i></span> <a href='mailto:email@domain.org'>email@domain.org</a><br>
                <span style='display:inline-block; font-weight:bold; padding-left:2em; padding-right:1em;'><i class='fas fa-phone'></i></span> <a href='tel:1234567890'>(123) 456-7890</a>";
    
    if (isset($holdResult['patron'])) {
        $patronWithHold = substr($holdResult['patron'],-7);
        
        //Make sure that expected patron ID was returned
        if($patronId == $patronWithHold) {			
			//Delete the hold
			$deleteResult = deleteHold($token, $holdId);
			if($deleteResult == NULL) {
                $type = false;
                $title = "Your hold on this item has been successfully canceled";
                $content = false;
			} else {
                $title = "There was a problem cancelling this hold";
                $result = json_decode($deleteResult, true);
                $content = "<span style='display:inline-block; text-align:center; width:100%;'><h3>" . $result['name'] . "</h3></span><br><br>" . $content;
            }
        } else {
            $title = "There was a problem cancelling this hold";
        }
	} else {
        $title = "This hold has already been cancelled";
    }

    //Build array to send back
    $array['type'] = $type;
    $array['title'] = $title;
    $array['content'] = $content;
    
    //Send back json encoded array
    echo json_encode($array);
?>