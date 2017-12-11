<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MerchantPoyalty ;

use Response;
use Log;  
use Exception;
use DB;

use Mail;

class UsersController extends Controller
 {
    //

		public function __Construct(){

			
		}


function authenticate(Request $request) {

    $username = $request->input('username');
    $password = $request->input('password');

try{
     $user = User::with('Merchant')
                        ->where([
     						['ur_user_login_id',$username ],
    					    ['ur_user_password', $password]
    					   ])
    			->get()->first();


       		if (! is_null($user) ) { 

                // authentication successful
              return Response::json(array('success'=> true ,'user'=>$user));

            } else {

                $usernameexits = User::with('Merchant')
                        ->where([
                            ['ur_user_login_id',$username]
                            ])           
                ->get()->first();

                // user does not exist ...
                if (is_null($usernameexits))
                {
                return Response::json(array('success' => false ,'msg'=>'username :'.$username.' does not exists , please contact administrator !'));
                }
                else {

                    return Response::json(array('success' => false ,'msg'=>'incorrect password ,please check your password.'));
               
                }
            }
        }
        catch(Exception $err) {
          // Fail Response
				return Response::json(array('success' => false , 'msg'=>$err->getMessage()));
        }
    }
	


    function resetpassword(Request $request) {

    $username = $request->input('email');
    $password = $request->input('password');

try{     

       Log::info("user id : ".$username); 


     //   DB::enableQueryLog();
        // check email id exists
        $user = User::where([
                 ['ur_user_login_id',$username ]
                       ])
                ->get()->first();

        // if exists  reset password 

      //  Log::info(DB::getQueryLog()); 

         if (is_null($user))
                {
                 return Response::json(array('success' => false ,'msg'=>'User Id :'.$username.' does not exists , Please contact administrator !'));
                }
                else {
                        $user['ur_user_password'] = 'welcome123';
                        $user->save();
                        $NotifiData = array( 
                            'MerchantName' =>$user['ur_user_name'],
                             'UserName'  => $user['ur_user_login_id'] ,
                             //'CustomerName' => $user ['ur_usermaster_id'] ,
                            'Password' => $user['ur_user_password'],
                            'CustomerEmail' =>  $user ['ur_user_email']);

                        $user['ur_user_password'] = 'welcome123';
                        $user->save();

                    $this-> sendEmail($NotifiData);    
                    return Response::json(array('success' => true ,'msg'=>'Your password has been reset,please check your email .'));               
                }
            
        }
        catch(Exception $err) {
          // Fail Response
                return Response::json(array('success' => false , 'msg'=>$err->getMessage()));
        }
    }




    public function sendEmail($data){


         Log::info('Sending Email  .....'.json_encode($data));
         $data = json_decode(json_encode($data), True);
         if  (!isset($data['CustomerEmail']) || trim($data['CustomerEmail'])==='')
         {  
                 Log::info('Invalid Email id is  :--'.$data['CustomerEmail'].'----for merchant :'. $data['MerchantName'] );

         }else {
                Log::info('Sending Email valid email id is  : '.$data['CustomerEmail']);

                   Mail::send('emailnotify', $data ,function ($message) use ($data) {

                      $message->from('support@poyalty.com', 'poyalty');
                      $message->subject('Password Recovery Notification - poyalty');
                      $message->replyTo("support@poyalty.com","poyalty");
                      $message->to( $data['CustomerEmail']);

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


	/*
function valiate(username, password) {
    var deferred = Q.defer();


    $user = User::find()
    			->where(['ur_user_login_id',username
    					'ur_user_password', password]) 
    			->get();

    db.users.findOne({ username: username }, function (err, user) {
        if (err) deferred.reject(err.name + ': ' + err.message);

        if (user && bcrypt.compareSync(password, user.hash)) {
            // authentication successful
            deferred.resolve({
                _id: user._id,
                username: user.username,
                firstName: user.firstName,
                lastName: user.lastName,
                token: jwt.sign({ sub: user._id }, config.secret)
            });
        } else {
            // authentication failed
            deferred.resolve();
        }
    });

    return deferred.promise;
}
*/



}




