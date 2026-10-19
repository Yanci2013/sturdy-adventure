<?php
session_start();

function isAdminLoggedIn()
{
    return isset($_SESSION['admin_id']) && intval($_SESSION['admin_id']) > 0;
}

function get_admin_id()
{
    return isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
}