<?php
/* This example implements a gateway to send and receive messages */
/* In essence is a continuous loop, first ask TDLib for messages received (includes status and other updates)
   and then looks for in a table if there are messages to send */
/* This code is thought-out to be run as daemon or background process with cli version of php */ 

//Initial Setup
define('APIID',NNNNNNN); //api_id — Application identifier for accessing the Telegram API, which can be obtained at https://my.telegram.org
define('APIHASH','XXXXXXXXX'); //api_hash — Hash of the Application identifier for accessing the Telegram API, which can be obtained at https://my.telegram.org
define('PHONENUMBER','+NNNNNNNNNN'); //phone number of the Telegram account you're going to use for the gateway, in international format
define('ACCID',NNNNNNNN); //ID of the account
define('DBPATH','/var/tdlib/db'); //location of TDLib database
define('DBFILES','/var/tdlib/files'); //Location of TDLib files
$acc_id=ACCID;

//Open MariaDB (Mysql) Database
mysqli_report(MYSQLI_REPORT_STRICT); 
try{
   $idbase = new mysqli('your DB host', 'your DB user', 'your DB pass', 'your DBase');
   $idbase->set_charset('utf8mb4');
}
catch (mysqli_sql_exception $e){
   echo 'Opening DB error: ',$e->getMessage();
   die();
}   
/* This example uses 3 tables:
mt: to store messages you send
mo: to store messages you receive
mid: to store phone number, chatID and userID of your contacts
*/

//Paths of modules can be different in your instalation, adjust them accordingly.

require ('/opt/tdlib-php-ffi/src/TDLib.php'); //tdlib-php-ffi module

try{
   $client=new Thisismzm\TdlibPhpFfi\TDLib('/opt/td/tdlib/lib/libtdjson.so'); //this is the compiled TDlib
   $cid=$client->createClientId();
   //Set verbosity log level and destiny
   $client->send($cid,json_encode(['@type'=>'setLogVerbosityLevel','new_verbosity_level'=>2]));
   $client->send($cid,json_encode(['@type'=>'setLogStream','log_stream'=>['@type'=>'logStreamFile','path'=>'/var/phplog/tldout.log','max_file_size'=>100000000,'redirect_stderr'=>true]]));
   //Initiate user authorization process
   $client->send($cid,json_encode(['@type'=>'getAuthorizationState']));
   //Infinite loop
   while (true){
      //Received messages and updates
      $res=$client->receive(1); //1 second timeout
      if (!is_null($res)){ //A null response means there's nothing to receive
         $msg=json_decode($res,true);
         if (isset($msg['@type'])){
            switch ($msg['@type']){ //analyze what type of message or update is
               case 'updateOption':
                  //Receive different parameters of present configuration, you can store them in your database or do something with them
                  //The only one it's used here is "my_id" which is the value of the account ID and serves to identify messages that are
                  //sent to me from those sent by me.
                  if (isset($msg['name'])){
                     switch($msg['name']){
                        case 'my_id': $acc_id=$msg['value']['value']; break; //the account ID, you can print or log this value to configure later
                        case 'authentication_token': $authtoken=$msg['value']['value']; break;
                        case 'unix_time': $acttime=date('Y-m-d H:i:s',$msg['value']['value'])); break;
                     }
                  }else{
                     //Not identified message, you can log it to analyze later 
                  }
                  break;
               case 'updateAuthorizationState':
                  //Part of authorization process
                  $ea=$msg['authorization_state']['@type'];
                  switch ($ea){
                     case 'authorizationStateWaitTdlibParameters': //TDLib is waiting for some parameters.
                        $cmd=['@type'=>'setTdlibParameters','use_test_dc'=>false,'database_directory'=>DBPATH, 'files_directory'=>DBFILES, 'use_file_database'=>true, 'use_chat_info_database'=>true, 'use_message_database'=>true,'use_secret_chats'=>false,'api_id'=>APIID, 'api_hash'=>APIHASH, 'system_language_code'=>'en','device_model'=>'your model','system_version'=>'your system','application_version'=>'your version', 'enable_storage_optimizer'=>true,'ignore_file_names'=>false];
                        $client->send($cid,json_encode($cmd));
                        break;
                     case 'authorizationStateWaitPhoneNumber': //Now you have to specify your phone number, TDLib will send a validation code to this number, it must have a Telegram client running on it
                        $cmd=['@type'=>'setAuthenticationPhoneNumber','phone_number'=>PHONENUMBER];
                        $client->send($cid,json_encode($cmd));
                        break;
                     case 'authorizationStateWaitCode':
                        /* Now is time to validate the code recieved in the phone. You can imagine and implement some trick to provide the validation code 
                        while you keep the loop running, but in this example my solution is to exit the loop and run again with the validation code passed as a 
                        command line parameter */
                        if ($argc><2){ //no parameter provided, means this is the first time run, exit the loop (remember that the first parameter is the application name, 
                                       //that's why I ask for 2 or more parameters).
                           exit;
                        }else{ //parameter is provided, then sent it to TDLib to validate.
                           $cmd=['@type'=>'checkAuthenticationCode','code'=>$argv[1]];
                           $client->send($cid,json_encode($cmd));
                        }   
                        break;
                     case 'authorizationStateReady': //Authorization is completed, this is informative
                        break;
                     case 'authorizationStateLoggingOut': //For any reason TDLib is logging out, informative
                        break;
                     case 'authorizationStateClosed': //The logout is completed, exit the loop to reiniciate the authorization process
                        exit;
                        break;
                     default:
                        //Not identified message, you can log it to analyze later 
                  }
                  break;
               case 'updateUser':
                  //Information about user. 
                  if ($msg['user']['id']==$acc_id){
                     //Information about self, nothing to do
                  }else{
                     //This is information about a contact, there's a lot of information in this message including images.
                     //Only interested in userID and chatID to update mid table.
                     if (isset($msg['user']['phone_number']) and $msg['user']['phone_number']>0){ //look for if it's already in mid table
                        $sql='select * from mid where msisdn=\''.$msg['user']['phone_number'].'\'';
                        if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error reading',1); }
                        if (mysqli_num_rows($result)>0){ //It's in mid table, compare if it's the same data
                           $row=mysqli_fetch_assoc($result);
                           mysqli_free_result($result);
                           if ($msg['user']['id']==$row['userid']){
                              //same userID, nothing to do
                           }else{
                              //different, update mid table and ask TDLib to create a chat with that userID,
                              //this is the way we have to obtain the chatID. If we had a chat with that contact in the past,
                              //TDLib will reopen that, on the contrary, it will create a new one.
                              //In either case, it will send an unpdate with the chatID.  
                              $sql='update mid set verif=1,userid='.$msg['user']['id'].' where msisdn=\''.$row['msisdn'].'\'';
                              if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error reading',1); }
                              $client->send($cid,json_encode(['@type'=>'createPrivateChat','user_id'=>$msg['user']['id'],'@extra'=>$row['msisdn']]));
                              sleep(1);
                           }
                        }else{
                           //We don't have this user in mid table, append it and ask TDLib to create a chat.
                           $sql='insert into mid (msisdn,userid,verif) values (\''.$msg['user']['phone_number'].'\','.$msg['user']['id'].',1)';
                           if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error reading',1); }
                           $client->send(json_encode($cid,['@type'=>'createPrivateChat','user_id'=>$msg['user']['id'],'@extra'=>$msg['user']['phone_number']]));
                           sleep(1);
                        }
                     }elseif(isset($msg['user']['type']['@type']) and $msg['user']['type']['@type']=='userTypeDeleted'){ //The contact was deleted, update mid table
                        $sql='update mid set verif=0,userid=0,chatid=0 where userid='.$msg['user']['id'];
                     }elseif(isset($msg['user']['phone_number']) and $msg['user']['phone_number']==''){
                        //The phone number of the contact is hidden, nothing to do
                     }else{
                        //Not identified message, you can log it to analyze later 
                     }
                  }
                  break;
               case 'updateNewMessage':
                  //This is an update of a new message
                  if ($msg['message']['sender_id']['user_id']!=$acc_id){ //this is a message a contact sent to me, I store it in mo table.                     
                     //in this example I do a minimum process of data, but the message can have images or files appended, you can recover them and do something
                     if (isset($msg['message']['content'])){
                        switch ($msg['message']['content']['@type']){
                           case 'text':
                              $mensaje=$msg['message']['content']['text']['text'];
                              break;
                           case 'messageText':
                              $mensaje=$msg['message']['content']['text']['text'];
                              break;
                           case 'messageDocument':
                              $mensaje='DOCUMENTO: '.$msg['message']['content']['document']['file_name'];
                              break;
                           case 'messageSticker':
                              if (isset($msg['message']['content']['sticker']['emoji'])){
                                 $mensaje=$msg['message']['content']['sticker']['emoji'];
                              }else{
                                 $mensaje='TIPO MENSAJE:'.$msg['message']['content']['@type'];
                              }
                              break;
                           case 'messageContactRegistered':
                              $mensaje='TIPO MENSAJE:'.$msg['message']['content']['@type'];
                              break;
                           default:
                              $mensaje='TIPO MENSAJE:'.$msg['message']['content']['@type'];
                              break;
                        }
                        $sql='insert into mo (userid,chatid,msgid,mensaje,fchrec) values ('.$msg['message']['sender_id']['user_id'].','.$msg['message']['chat_id'].','.$msg['message']['id'].',\''.mysqli_real_escape_string($idbase,$mensaje).'\',\''.date('Y-m-d H:i:s',$msg['message']['date']).'\')';
                        if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                     }else{
                        //Not identified message, you can log it to analyze later
                     }   
                  }else{
                     //This is a message I sent, nothing to do
                  }
                  break;
               case 'message':
                  //Information of a message
                  if ($msg['sender_id']['user_id']==$acc_id){ //This is a message I sent, update mt table with status 3: message was sent
                     $sql='update mt set estado=3,fchenv=\''.date('Y-m-d H:i:s',$msg['date']).'\',msgid='.$msg['id'].' where idmt='.$msg['@extra'];
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                  }else{
                     //Not identified message, you can log it to analyze later
                  }
                  break;
               case 'updateMessageSendSucceeded':
                  //This update indicates message reached destiny
                  if ($msg['message']['sender_id']['user_id']==$acc_id){ //This is the message I sent, update mt table with status 4
                     $sql='update mt set estado=4,fchent=\''.date('Y-m-d H:i:s',$msg['message']['date']).'\',msgid='.$msg['message']['id'].',coderr=\'\' where chatid='.$msg['message']['chat_id'].' and msgid='.$msg['old_message_id'];
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                  }else{
                     //Not identified message, you can log it to analyze later
                  }
                  break;
               case 'updateMessageSendFailed':
                  //Sending of message failed,
                  if ($msg['message']['sender_id']['user_id']==$acc_id){ //This is the message I sent, update mt table with status 2
                     $sql='update mt set estado=2,fchpro=\''.date('Y-m-d H:i:s',$msg['message']['date']).'\',msgid='.$msg['message']['id'].',coderr=\''.$msg['error_code'].'-'.$msg['error_message'].'\' where chatid='.$msg['message']['chat_id'].' and msgid='.$msg['old_message_id'];
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                  }else{
                     //Not identified message, you can log it to analyze later
                  }
                  break;
               case 'updateChatReadOutbox':
                  //This update indicates contact readed message
                  $sql='update mt set estado=5,fchlei=\''.date('Y-m-d H:i:s').'\' where chatid='.$msg['chat_id'].' and msgid='.$msg['last_read_outbox_message_id'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                  break;
               case 'chat':
                  //This is the update with the chatID I was waiting
                  $sql='update mid set chatid='.$msg['id'].',verif=2 where msisdn=\''.$msg['@extra'].'\'';
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                  //reactivate records in mt table they were previously put in pending state
                  $sql='update mt set estado=0 where estado=1 and msisdn=\''.$msg['@extra'].'\'';
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                  break;
               case 'importedContacts':
                  //This is an update of the import contact I sent before
                  if ($msg['user_ids'][0]==0){
                     //The phone number requested has no Telegram account, update mid table
                     $sql='update mid set userid=0,chatid=0,verif=2 where msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                     //update all records in mt table that were previously put in pending state
                     $sql='update mt set estado=2,coderr=\'0-No existe contacto\',fchpro=\''.date('Y-m-d H:i:s').'\' where estado=1 and msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                  }else{
                     //The phone number requested has a Telegram account, update mid table with userID
                     $sql='update mid set userid='.$msg['user_ids'][0].' where msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                     //I request TDLib to create or reopen a chat
                     $client->send($cid,json_encode(['@type'=>'createPrivateChat','user_id'=>$msg['user_ids'][0],'@extra'=>$msg['@extra']]));
                     sleep(1);
                  }   
                  break;
               case 'error': //TDLib can sent error messages
                  if ($msg['code']==429 and substr($msg['message'],0,17)=='Too Many Requests'){
                     //This error usually indicates the phone number I tried to contact has no Telegram account, update mid table
                     $sql='update mid set userid=0,chatid=0,verif=3 where msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                     //update all records in mt table they were previously put in pending state 
                     $sql='update mt set estado=2,coderr=\'0-Error en contacto\',fchpro=\''.date('Y-m-d H:i:s').'\' where estado=1 and msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                  }elseif($msg['code']==400 and $msg['message']=='Chat not found'){
                     //chatID of the contact has changed (from what I have registered in the past), I recover the phone number to ask TDLib again to create a chat
                     $sql='select msisdn from mt where idmt='.$msg['@extra'];
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error reading',1); }
                     $row=mysqli_fetch_assoc($result);
                     mysqli_free_result($result);
                     $sql='update mt set estado=1 where idmt='.$msg['@extra']; //put all registers in mt table in pending state
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                     $sql='select userid from mid where msisdn=\''.$row['msisdn'].'\''; //recover userID 
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error reading',1); }
                     $usu=mysqli_fetch_assoc($result);
                     mysqli_free_result($result);
                     $sql='update mid set verif=1 where msisdn=\''.$row['msisdn'].'\''; //put record in mid table in pending state
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                     $client->send($cid,json_encode(['@type'=>'createPrivateChat','user_id'=>$usu['userid'],'@extra'=>$row['msisdn']])); //ask TDLib
                     sleep(1);
                  }else{
                     //Not identified message, you can log it to analyze later
                  }
                  break;
               //The following types of updates have no usage on this example, I do nothing with them
               case 'authorizationStateWaitTdlibParameters':
               case 'ok':   
               case 'updateSuggestedActions':
               case 'updateMessageInteractionInfo':
               case 'updateChatTitle':   
               case 'updateChatAction':
               case 'updateChatLastMessage':
               case 'updateDeleteMessages':
               case 'updateChatReadInbox':
               case 'updateUserFullInfo':
               case 'updateChatNotificationSettings':
               case 'updateNewChat':
               case 'updateAnimationSearchParameters':
               case 'updateReactions':
               case 'updateUserStatus':
               case 'updateConnectionState':
               case 'updateHavePendingNotifications':
               case 'updateConnectionState':
               case 'updateChatThemes':
               case 'updateChatFilters':
               case 'updateScopeNotificationSettings':
               case 'updateUnreadChatCount':
               case 'updateChatActionBar':
               case 'updateChatPhoto':
               case 'updateDefaultReactionType':
               case 'updateAttachmentMenuBots':
               case 'updateSelectedBackground':
               case 'updateFileDownloads':
               case 'updateDiceEmojis':
               case 'updateActiveEmojiReactions':
               case 'updateChatFolders':
                  break;
               default:
                  //Not identified message, you can log it to analyze later
            }
         }else{
            //Not identified message, you can log it to analyze later
         }
      }else{ //There's nothing received, I look for in the mt table if I have a new record to send.
         $sql='select mt.idmt,mt.mensaje,mt.msisdn,mid.userid,mid.chatid,mid.verif from mt left join mid on mt.msisdn=mid.msisdn where mt.estado=0 order by mt.idmt limit 1';
         if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error reading',1); }
         if (mysqli_num_rows($result)==1){ //There's something to send
            $row=mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            if (is_null($row['verif'])){ //I don't have the phone number in mid table (this is the first time I send a message to this contact), put the record in pending state
               $sql='update mt set estado=1,fchpro=\''.date('Y-m-d H:i:s').'\' where idmt='.$row['idmt'];
               if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
               //append a record to mid table 
               $sql='insert into mid (msisdn,verif) values (\''.$row['msisdn'].'\',0)';
               if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
            }else{ //I have a record in mid table
               if ($row['userid']>0 and $row['chatid']>0){ //I have a userID and chatID of this contact I send the message
                  $sql='update mt set estado=3,fchpro=\''.date('Y-m-d H:i:s').'\',fchenv=\''.date('Y-m-d H:i:s').'\',userid='.$row['userid'].',chatid='.$row['chatid'].' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  $acc=json_encode(['@type'=>'sendMessage','chat_id'=>$row['chatid'],'message_thread_id'=>0,'reply_to_message_id'=>0,'input_message_content'=>array('@type'=>'inputMessageText','text'=>['@type'=>'formatedText','text'=>$row['mensaje']]),'@extra'=>$row['idmt']]);
                  $client->send($cid,$acc);
                  sleep(1);
               }elseif ($row['verif']==2){ //I have a record in mid table but in the past the contact didn't have a Telegram account
                  //I put the mt record in pending state and I will ask TLDlib again about the contact
                  $sql='update mt set estado=1,fchpro=\''.date('Y-m-d H:i:s').'\',coderr=\'0-No existe contacto\' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
                  $sql='update mid set verif=0 where msisdn=\''.$row['msisdn'].'\'';
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
               }elseif ($row['verif']==3){ //Last verification of the contact TLDlib responded with error 429, I mark mt record with error
                  $sql='update mt set estado=2,coderr=\'0-Error en contacto\',fchpro=\''.date('Y-m-d H:i:s').'\' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
               }elseif ($row['verif']>3){ //In case we decided to exclude this contact for any reason
                  $sql='update mt set estado=2,coderr=\'0-Usuario bloqueado\',fchpro=\''.date('Y-m-d H:i:s').'\' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
               }else{ //I'm waiting for TDLib to complete chat creation, I put the mt record in pending state
                  $sql='update mt set estado=1,fchpro=\''.date('Y-m-d H:i:s').'\' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
               }
            }
         }else{
            //In this part, I look for in mid table if we have a phone number of a contact that is pending to update, that means I don't have the userID nor the chatID
            //To obtain that information, the first step is to request TDLib to import a contact
            $sql='select * from mid where verif=0 order by fchver limit 1';
            if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error reading',1); }
            if (mysqli_num_rows($result)==1){
               $row=mysqli_fetch_assoc($result);
               mysqli_free_result($result);
               $client->send($cid,json_encode(['@type'=>'importContacts','contacts'=>[['@type'=>'contact','phone_number'=>'+'.$row['msisdn'],'user_id'=>0]],'@extra'=>$row['msisdn']]));
               sleep(1);
               $sql='update mid set verif=1 where msisdn=\''.$row['msisdn'].'\''; //mark mid record in pending state
               if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error writing',1); }
             }
         }else{ //nothing to receive, nothing to send, make a pause to not overload.
            sleep(1);
         }
      }
   }
}
catch (\Exception $e) {
   echo 'Error: ',$e->getMessage();
}
?>
