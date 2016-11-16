<?php
/**
 * Created by mohuishou<1@lailin.xyz>.
 * User: mohuishou<1@lailin.xyz>
 * Date: 2016/11/14 0014
 * Time: 16:11
 */
use App\Models\User;
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ ."/database/start.php";
$phone="1234566";
$user=User::firstOrCreate(["phone"=>$phone]);
$uid=$user->id;
print_r($user);

