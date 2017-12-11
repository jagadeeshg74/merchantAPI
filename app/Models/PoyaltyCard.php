<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Customer;


class PoyaltyCard extends Model
{
    //

	protected $table ='pty_cust_poyalty_card_hdr';

	protected $primaryKey = 'cp_id';
	  public  $timestamps =false;

    protected $fillable = [
        
    ];	

    public function customer() {
        return $this->belongsTo('App/Models/Customer','CM_CUST_ID');
    }



}
