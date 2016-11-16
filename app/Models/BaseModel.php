<?php
/**
 * Created by mohuishou<1@lailin.xyz>.
 * User: mohuishou<1@lailin.xyz>
 * Date: 2016/11/14 0014
 * Time: 19:05
 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    protected $dateFormat="U";

    protected $guarded=['id'];
}