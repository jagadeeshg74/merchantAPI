<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers;

use App\Models\Redeem ;
use App\Models\MerchantPoyalty;
use App\Models\MerchantPoyaltyDtl ;
use App\Models\PoyaltyCard;

use Response;
use Log;  
use Validator;
use Exception;
use ValidationException;
use ValidatesRequests;
use Illuminate\Support\Facades\DB;
use Mail;
use PHPMailer;

use  Illuminate\Mail\MailServiceProvider;
use  Illuminate\Mail\TransportManager;


class RedeemController extends Controller
{
    //

	public function __Construct(){

		}
    
    public function index(Request $request){

        return Response::json(array('success' => true));
    }


	public function store(Request $request)
	 {

           DB::enableQueryLog();

    	 	 // get the Request Object's value and store.....

            $cust_id       = $request->input('cust_id');
            $merchant_id   = $request->input('merchant_id');
            $bill_amount   = $request->input('bill_amount');
            $redeem_amount = $request->input('redeem_amount');
            $final_amount  = $bill_amount - $redeem_amount;
            $bill_no       = $request->input('bill_no');
            $bill_date     = $request->input('bill_date');
            $current_balance =0 ;
            $new_balance =0;

            Log::info('******************  Accrue Redeem Request  **********************');
            Log::info(' Inputs  Bill_amount , Redeem amount  Bill Date  '.$bill_amount.'-------'. $redeem_amount.'----'. $bill_date );

        //  Validate the required fields 

            $validator = Validator::make($request->all(), [
            'bill_date'       => 'required|date',
            'bill_no'         => 'required',
            'bill_amount'     =>  'required | numeric |min:1',
            'cust_id'         => 'required',
            'merchant_id'     => 'required',
            // Todo : add validation for redeem amount < bill_amount     

        ]);


        $Customer = DB::table('pty_cust_master')
                           ->where( 'pty_cust_master.cm_cust_id', '=', $cust_id)
                          ->select('pty_cust_master.cm_Name','pty_cust_master.cm_town','pty_cust_master.cm_address_1','pty_cust_master.cm_mobile_no','pty_cust_master.cm_email')
                           ->get()->first();
        $WorkingArray = json_decode(json_encode($Customer),true);
                          
        Log::info('valid Customer : ' .json_encode($Customer));


        $NotifiData = array( 'CustomerId' =>$cust_id,
                             'MerchantId' => $merchant_id,
                            'CustomerName' => $WorkingArray ['cm_Name'] ,
                            'CustomerMobile' => $WorkingArray ['cm_mobile_no'],
                            'CustomerEmail' =>  $WorkingArray ['cm_email']);

       $Merchant = DB::table('pty_merchant_master')
                   ->where( 'pty_merchant_master.mm_merchant_id', '=', $merchant_id )
                  ->select('pty_merchant_master.mm_Name','pty_merchant_master.mm_town','pty_merchant_master.mm_address_1')
                   ->get()->first();

         $WorkingArray = json_decode(json_encode($Merchant),true);
        Log::info('Valid Merchant ----------' .json_encode($Merchant));


        $NotifiData['MerchantName'] = $WorkingArray['mm_Name'] ;
        $NotifiData['MerchantTown'] = $WorkingArray['mm_town'] ;
        $NotifiData['MerchantAddress'] = $WorkingArray['mm_address_1'] ;
       

       // create notifcation array 
       $notific_list = array();  
        $n_index =0;       
        //Get Poyalty card exists ..



      DB::beginTransaction();
try
        {    
      if ($validator->fails()) {
      Log::info('Validation Errors occured '.$validator->messages());
      throw new Exception( 'Validation Errors occured '.$validator->messages());         
         // todo Throw validation Exception    
      }

                

      // Poyalty Card XREF      
    $poyaltyCardXref = DB::table('pty_cust_poyalty_card_xref')
                        ->where('cl_cust_id',$cust_id)->get()->first();

             
        if (is_null($poyaltyCardXref)){
                Log::info('Poyalty Card does not exist for this customer .');
                 throw new Exception ('Poyalty Card does not exist for given Customer :'.$NotifiData ['CustomerName'].' cubrid_connect_with_url(conn_url) Customer Id '.$cust_id) ;
          }      
         //  Get the poyalty card details for the customer 

            $poyaltycard= PoyaltyCard::where('cp_cust_id',$cust_id)->get()->first();

                 if (is_null($poyaltycard)){
                    Log::info('Poyalty Card Header does not exist for this customer .');
                    // throw new Exception ('Poyalty Card does not exist for given Customer :'.$cust_id) ;

             //  PTY_CUST_POYALTY_CARD_HDR  
            DB::table('pty_cust_poyalty_card_hdr')->insert(
                [
                  'cp_cust_id' =>  $cust_id ,
                  'cp_card_id'  =>  1,
                  'cp_poyals_accrued'=>   0 ,
                  'cp_poyals_redeemed' =>   0 ,
                  'cp_poyals_expired' =>  0  ,
                  'cp_poyals_balance' =>  0 ,
                  'cp_record_status' => 'A'  ,
                  'cp_create_date'=> DB::raw('CURDATE()')
                ]
            );
       
             Log::info(' PTY_CUST_POYALTY_CARD_HDR  record created.');  
             $poyaltycard= PoyaltyCard::where('cp_cust_id',$cust_id)->get()->first();


                 }else {
                        Log::debug('Poyalty Card  : '.json_encode($poyaltycard)); 
                 }      

         // Get poyalty merchant  header

                 $merch_poyalty_hdr = MerchantPoyalty::where([
                                    ['mp_cust_id',"=", $cust_id ],
                                    ['mp_merchant_id' ,"=",$merchant_id],
                                    ['mp_record_status',"=",'A'],
                                    ['mp_card_id',"=", $poyaltycard['cp_card_id']]  
                                  ])->get()->first();


                if (is_null($merch_poyalty_hdr) ){  //  merchant poyalty header record doest not exist create one ...
                        $merch_poyalty_hdr = new MerchantPoyalty([
                        'mp_cust_id' =>  $cust_id ,
                        'mp_merchant_id' => $merchant_id ,
                        'mp_card_id' =>  1 ,
                        'mp_poyals_balance' =>  floor($bill_amount/20) , 
                        'mp_poyals_accrued' =>  floor($bill_amount/20) ,
                        'mp_record_status' =>'A',
                        'mp_create_date'  =>  date("Y/m/d")
                        ]);

              //  Log::info('Merchant Poyalty header  ...creating....... ');
                        if ($bill_amount > 20 ) {         // Create Accrued Record ...
                                Log::info('Creating accrued record ... for final bill amount'.$final_amount );
                                //  add  details 
                              // generate transation id 
                                $transactionid=$merchant_id.date("ymd").$this->getTransCounter();
                                $merch_dtl_accrued = new MerchantPoyaltyDtl ([

                                'pd_cust_id'            => $cust_id ,
                                'pd_merchant_id'        =>$merchant_id ,
                                'pd_card_id'            => $merch_poyalty_hdr['mp_card_id'] ,
                                'pd_transaction_id'     => $transactionid ,
                                'pd_transaction_type'   => 'A', //Accrued
                                'pd_poyals_added'       =>  floor($bill_amount/20),
                                'pd_poyals_balance'     =>  floor($bill_amount/20) ,
                                'pd_merchant_bill_No'   => $bill_no ,
                                'pd_merchant_bill_date' => $bill_date ,
                                'pd_create_date'       => date("Y/m/d")

                                ]);   

                                $merch_dtl_accrued -> save();

                                Log::info('Merchant Poyalty Detail Accrued record created ');

                                }
                        }  
                       else //  Update Poyalty Merchant Header ...   Redeem Accrued Scenario 
                        { 

                            $current_balance  =  $merch_poyalty_hdr['mp_poyals_balance'] ;
                      
             //      Log::info('Poyalty Merchant Card retrieved , Poyals_balance : _____'.$merch_poyalty_hdr['mp_poyals_balance']); 

                            if ($redeem_amount ) //    create redeem  record
                            {

                          //   Log::info($redeem_amount.'==============='.$current_balance);
                            if ($redeem_amount > $current_balance ){
                                    throw new Exception ('Redeem amount ..'.$redeem_amount.' ..cannot be greater than available cash points ..'.$current_balance);
                            }
                            else {
                              //      Log::info('__________  Creating MPD Redeem Record ________Redeem amount _____'.$redeem_amount);
                                 //    $new_balance = $current_balance - $redeem_amount ;

                               //     Log::info('__________  Creating MPD Redeem Record ________New Balance _____'.$new_balance);
                               
                                   $new_balance = $current_balance - $redeem_amount ;
                                   // generate transation id 
                                   $transactionid=$merchant_id.date("ymd").$this->getTransCounter();     
                                    $merch_dtl_redeem = new MerchantPoyaltyDtl ([

                                    'pd_cust_id'            => $cust_id ,
                                    'pd_merchant_id'        => $merchant_id ,
                                    'pd_card_id'            => $merch_poyalty_hdr['mp_card_id'] ,
                                    'pd_transaction_id'     => $transactionid ,
                                    'pd_poyals_redeemed'    => $redeem_amount ,
                                    'pd_transaction_type'   => 'R' ,  
                                    'pd_poyals_balance'     =>  $new_balance, 
                                    'pd_merchant_bill_No'   => $bill_no ,
                                    'pd_merchant_bill_date' => $bill_date ,
                                    'pd_create_date'       => date("Y/m/d")

                                    ]);   
                                    $merch_dtl_redeem ->save(); 

                                Log::info('Created Merchant Poyalty Detail redeem record .');

                                // send Notification 0
               
                        $NotifiData['TransType'] = 'Redeemed' ;
                        $NotifiData['Cashpoints'] = $redeem_amount ;
                        $NotifiData['BalanceCashpoints'] = $new_balance ;
                       //  $this->sendNotifications($NotifiData); 
                        $notific_list[ $n_index++] = $NotifiData ;


Log::info(' Redeem notifications sent .');
//Log::info('NotifiData ----------' .json_encode($NotifiData));

// Log::info('__________   MPD Redeem Record  Created ________Poyals Balance _____'.$merch_dtl_redeem['pd_poyals_balance']);

                    $merch_poyalty_hdr -> mp_poyals_redeemed += ($redeem_amount);
                    $merch_poyalty_hdr -> mp_poyals_balance   = $new_balance;


                            if ($final_amount > 20 ) {         // Create Accrued Record ...

                                    $new_balance = $current_balance - $redeem_amount + floor($final_amount/20) ;
                                    Log::info('Creating accrued record ... for final bill amount : '.$final_amount );
                                    //  add  details 
                                    // generate transation id 
                                    $transactionid=$merchant_id.date("ymd").$this->getTransCounter();
                                    $merch_dtl_accrued = new MerchantPoyaltyDtl ([

                                    'pd_cust_id'            =>  $cust_id ,
                                    'pd_merchant_id'        =>  $merchant_id ,
                                    'pd_card_id'            =>  $merch_poyalty_hdr['mp_card_id'] ,
                                    'pd_transaction_id'     =>  $transactionid ,
                                    'pd_transaction_type'   =>  'A', //Accrued
                                    'pd_poyals_added'       =>  floor($final_amount/20),
                                    'pd_poyals_balance'     =>  $new_balance ,
                                    'pd_merchant_bill_No'   =>  $bill_no ,
                                    'pd_merchant_bill_date' =>  $bill_date,
                                    'pd_create_date'      => date("Y/m/d")

                                    ]);   

                                    $merch_dtl_accrued -> save();
                            Log::info('Created Merchant Poyalty Detail accrued record .');


                             // send Notification 1
                               
                                $NotifiData['TransType'] = 'Accrued' ;
                                $NotifiData['Cashpoints'] = floor($final_amount/20) ;
                                $NotifiData['BalanceCashpoints'] = $new_balance ;
                                //  Log::info('NotifiData   1----------' .json_encode($NotifiData));
                               // $this->sendNotifications($NotifiData);
                                 $notific_list[ $n_index++] = $NotifiData ;


                            Log::info(' Accrued notifications sent .');    

                                    $merch_poyalty_hdr -> mp_poyals_balance   = $new_balance;
                                    $merch_poyalty_hdr -> mp_poyals_accrued  += floor($final_amount/20) ; 

                                    }  //   if final amount  ....

                                }   
                                }   ///redeem amount 
                                else //  create a accrued record 
                                {

                                    if ($final_amount > 20 ) {         // Create Accrued Record ...

                                    $new_balance = $current_balance  + floor($final_amount/20) ;
                                    Log::info('Creating accrued record for final bill amount ____'.$final_amount );
                                    //  add  details 
                                    $transactionid=$merchant_id.date("ymd").$this->getTransCounter();
                                    $merch_dtl_accrued = new MerchantPoyaltyDtl ([

                                    'pd_cust_id'            => $cust_id ,
                                    'pd_merchant_id'        =>$merchant_id ,
                                    'pd_card_id'            => $merch_poyalty_hdr['mp_card_id'] ,
                                    'pd_transaction_id'     => $transactionid ,
                                    'pd_transaction_type'   => 'A', //Accrued
                                    'pd_poyals_added'       =>  floor($final_amount/20),
                                  //  'pd_poyals_balance'     => $current_balance  + floor($final_amount/20) ,
                                    'pd_poyals_balance'     => $new_balance,
                                    'pd_merchant_bill_No'   => $bill_no ,
                                    'pd_merchant_bill_date' => $bill_date ,
                                    'pd_create_date'      => DB::raw('CURDATE()')

                                    ]);   

                                    $merch_dtl_accrued -> save();

                                Log::info('Created Merchant Poyalty Detail accrued record .');

                              // send Notification 2
                                 
                                $NotifiData['TransType'] = 'Accrued';
                                $NotifiData['Cashpoints'] = floor($final_amount/20) ;
                                $NotifiData['BalanceCashpoints'] = $new_balance ;
                                Log::info('NotifiData   2----------' .json_encode($NotifiData));
                              //  $this->sendNotifications($NotifiData);
                                 $notific_list[ $n_index++] = $NotifiData ;

                                Log::info(' Accrued notifications sent .');  

                            $merch_poyalty_hdr -> mp_poyals_balance   = $new_balance;
                            $merch_poyalty_hdr -> mp_poyals_accrued  += floor($final_amount/20) ;             


                             //       Log::info('Createc Merchant Poyalty Detail  accrued : _____' . $merch_dtl_accrued['pd_poyals_added'] .'____Balance : '.$merch_dtl_accrued['pd_poyals_balance']);

                                    } 
                                }

                            }//  else.........///update mechant header 
          
                   if ($merch_poyalty_hdr -> save())
                        {
                            $merch_poyalty_hdr = MerchantPoyalty::findOrFail($merch_poyalty_hdr['mp_id']);
                            Log::info('Merchant Poyalty header completed  '.$merch_poyalty_hdr['merch_poyalty_hdr']);
                        }
                        else {
                            Log::info('error in creating /updating Merchant Poyalty Header ');
                           throw new Exception ('Error in creating /updating Merchant Poyalty Header '); 
                      }     

        // Update Poyalty Card **************** // *****************
         $poyaltycard->cp_poyals_accrued   += floor($final_amount/20);
         $poyaltycard->cp_poyals_redeemed  += $redeem_amount;
         $poyaltycard->cp_poyals_balance    =  $poyaltycard->cp_poyals_accrued - $poyaltycard->cp_poyals_redeemed - $poyaltycard->cp_poyals_expired;
         $poyaltycard->save();
         Log::debug(' Poyalty Card Updated  _____cp_poyals_balance : '.$poyaltycard['cp_poyals_balance']);
      //   Log::debug(' Poyalty Card  : '.$poyaltycard);
       //  Log::debug(' Merchant Poyalty Header  : '.$merch_poyalty_hdr);
         Log::info('***************   Accrue Redeem Request Completed      ***************************' );



          //  PTY_CUST_MERCHANT_BILL  
            DB::table('pty_cust_merchant_bill')->insert(
                [
                  'mb_cust_id' =>  $cust_id ,
                  'mb_merchant_id' => $merchant_id   ,
                  'mb_card_id'  =>  1,
                  'mb_transaction_id' => $merchant_id.date("ymd").$this->getTransCounter()  ,
                  'mb_merchant_bill_No' =>  $bill_no ,
                  'mb_merchant_bill_date'  =>  $bill_date ,
                  'mb_bill_amount'=>    $bill_amount  ,
                  'mb_poyals_redeemed' =>   is_null($redeem_amount) ? 0: $redeem_amount ,
                  'mb_amount-paid_by_poyals' =>  is_null($redeem_amount) ? 0: $redeem_amount*20   ,
                  'mb_final_amount-paid' =>  $final_amount  ,
                  'mb_record_status' => 'A'  ,
                  'mb_create_date'=> DB::raw('CURDATE()')
                ]
            );
       
             Log::info(' PTY_CUST_MERCHANT_BILL  record created.');  

     //  Log::info(DB::getQueryLog());  

               DB::commit();
               Log::info(' Accrue Redeem transaction successfully completed .');  

               try{
                     foreach ($notific_list as $key => $value) {
                          // $arr[3] will be updated with each value from $arr...
                          Log::info("{$key} => {} ");
                          $this->sendNotifications($value);
                         
                      }
            }
            catch(Exception $excep)
            {
                    Log::debug('********  Exception occured in sending email : '.$excep->getMessage().'****************');     

            }

        return Response::json(array('success' => true ,'poyalty_card' => $poyaltycard,'merchant_poyalty'=>$merch_poyalty_hdr));

    }
    catch (Exception $excep)
    {
         DB::rollback();
         Log::debug('********  Exception occured in customer redeem  : '.$excep->getMessage().$excep->getTraceAsString().'****************');     
         return Response::json(array('success' => false ,'msg' => $excep->getMessage()));

    }
	
    }


    public function getTransCounter (){

    DB::update("UPDATE pty_system_settings
                       SET ss_settings_Val_1 = (@cur_value := ss_settings_Val_1) + 1
                       WHERE ss_settings_key ='TRANSCOUNT'" ); 

                     $counter_no = DB::select("SELECT @cur_value" );
                     $count =(array)$counter_no[0];
              //       Log::info('counter id' . var_export($counter_no, true) );
               //      Log::info ('count'.$count['@cur_value']);                       
                     $counter_no = DB::select("SELECT @cur_value" );
                     $count =(array)$counter_no[0];
            
                     return $count['@cur_value'];


    }


//  Send Notifications 


    public function sendNotifications($data){

      Log::info('Data  : '.json_encode($data));

         DB::table('pty_system_notifications')->insert(
                  [
                    'sx_cust_id' =>  $data['CustomerId'] ,
                    'sx_notification_date' => date("Y/m/d") ,
                    'sx_create_date' => date("Y/m/d") ,
                    'sx_notification_time' =>date("H:i:sa"), 
                    'sx_notificatin_short_message' => $data['TransType'] . " ".$data['Cashpoints'] ." on ".date("M d,Y")." at ".$data['MerchantName']." ,". $data['MerchantTown'] ." with the current balance of". $data['BalanceCashpoints'] .".",
                    'sx_notificatin_Long_message'=>
                    $data['TransType'] . " ". $data['Cashpoints']." on ". date("M d,Y")."  at ".$data['MerchantName']." ,". $data['MerchantTown'] ." with the current balance of ". $data['BalanceCashpoints'] .". Pl. use poyalty cashpoints and save your money."
                  ]
              );

            try {
                  $this->sendMessage($data);
                  Log::info('SMS Completed .');
            }

            catch (Exception $excep){

               Log::debug('********  Exception occured in customer redeem SMS : '.$excep->getMessage().$excep->getTraceAsString().'****************');
            }    

         try{
              $this->sendEmail($data);
            Log::info('Email Completed .');
        }
          catch(Exception $excep){
             Log::debug('********  Exception occured in customer redeem email : '.$excep->getMessage().$excep->getTraceAsString().'****************');
          
        }
         
        }

      


    public function sendMessage($data){

           $msg = $data['TransType'] . " ".$data['Cashpoints'] ." on ".date("M d,Y")." at ".$data['MerchantName']." ,". $data['MerchantTown'] ." with the current balance of". $data['BalanceCashpoints'] .".";
     
    
        $msg = str_replace(" ","%20",$msg);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "http://online.chennaisms.com/api/mt/SendSMS?user=ommanivannan&password=abcd123&senderid=poylty&channel=Trans&DCS=0&flashsms=0&number=".$data['CustomerMobile']."&text=".$msg."&route=28");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
          curl_close($curl);


 
        }



      public function sendEmail($data){


         Log::info('Sending Email  .....'.json_encode($data));
         $data = json_decode(json_encode($data), True);
         if  (!isset($data['CustomerEmail']) || trim($data['CustomerEmail'])==='')
         {  
                 Log::info('Invalid Email id is  :--'.$data['CustomerEmail'].'----for customer :'. $data['CustomerName'] );

         }else {
                Log::info('Sending Email valid email id is  : '.$data['CustomerEmail']);

                   Mail::send('redeemnotify', $data ,function ($message) use ($data) {

                      $message->from('support@poyalty.com', 'poyalty');
                      $message->subject('Redeem/Accrue Notification - poyalty');
                      $message->replyTo("support@poyalty.com","poyalty");
                      $message->to( $data['CustomerEmail'] );

                  });

                if(count(Mail::failures()) > 0){
                    
                    Log::debug("There was one or more failures in sending email . They were: <br />");

                   foreach(Mail::failures() as $email_address) {
                       Log::debug( " Email failed for - $email_address- <br />");
                    }

                }else
                {
                //  Log::info('mail sent successfully !');
                  Log::info('Email Completed .');
                }
      }
  }


public function sendEmail12(){
  // Create the Transport


  Mail::raw('Text to e-mail', function ($message) {

          $message->from('support@poyalty.com', 'poyalty');
          $message->subject('Redeem/Accrue Notification - poyalty');
          $message->replyTo("support@poyalty.com","poyalty");
          $message->to( 'jagadeshg@gmail.com' );

      });

}
          /*  

           $data=array('custname' =>"Jagadesh" ,
            'Mobile'=>9566059483,
           'TransType' => 'Accrued' ,
           'Cashpoints'=> '1500' ,
           'Merchant_name'=>'Kofee Treat', 
           'Town'=>'Chennai', 
           'Balancepoints'=>'1000');
          
            */

}