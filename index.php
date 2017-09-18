<?php
include "message_processing.php";

// Group's access token
$token = 'ab6f270e4e2da10a1304770c6154fce9f712ba3dbcb0810a64027678a5b321c6ab526d449bb21bacd3e7e'; 

if (!isset($_REQUEST)) { 
    return;
}

$data = json_decode(file_get_contents('php://input')); 

if ($data->type == "message_new") {
    $user_id = $data->object->user_id; 
    $message = $data->object->body;
    
    $was_corrected;
    $corrected_message = process_message($message, $was_corrected);
    $response;
    
    if ($was_corrected) {
        $response = "Возможно вы имели в виду: \n".$corrected_message;
    } else {
        $response = "-";
    }
    
    $request_params = array( 
      'message' => $response, 
      'user_id' => $user_id, 
      'access_token' => $token, 
      'v' => '5.0' 
    ); 

    $get_params = http_build_query($request_params); 

    file_get_contents('https://api.vk.com/method/messages.send?'. $get_params); 

    echo('ok');
}

?>