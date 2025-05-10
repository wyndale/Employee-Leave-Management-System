<?php
function redirect($url, $message = null, $messageType = 'success') {
    if ($message) {
        Session::set('message', $message);
        Session::set('message_type', $messageType);
    }
    header("Location: $url");
    exit;
}
?>