<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Merchant;

class User extends Model
{
    //

	protected $table ='pty_user_master';

	protected $primaryKey = 'ur_usermaster_id';

	protected $foreignkey ="ur_merchant_id" ;

   public  $timestamps =false;
    
    protected $fillable = [
    'ur_user_password'      
    ];


    public function merchant()
    {
        return $this->belongsTo('App\Models\Merchant',  'ur_merchant_id','mm_merchant_id');
    }
  



}
