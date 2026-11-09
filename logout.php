<?php
session_start();
unset($_SESSION['groceria_user']);
header('Location: login.php');
exit;