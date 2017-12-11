<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantPoyaltyDtl extends Model
{
    //

	protected $table ='pty_cust_poyalty_card_merchant_dtl';

    public  $timestamps =false;

    protected $fillable = [
        
        'pd_cust_id' ,
        'pd_merchant_id',
        'pd_card_id',
        'pd_nom_id',
        'pd_transaction_id',
        'pd_transaction_type',
        'pd_poyals_added',
        'pd_poyals_redeemed',
        'pd_poyals_balance',
        'pd_merchant_bill_No',
        'pd_merchant_bill_date',
        'pd_record status'   ,
        'pd_create_date' ,    
    ];



}
