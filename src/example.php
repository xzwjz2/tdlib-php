<?php
THIS WORK IS IN PROGRESS, IF YOU TRY TO USE IT, IT'S SURE IT WILL BE VERY DIFFICULT TO UNDERSTAND IT.
  
/* This example implements a loop to receive and send messages */
/* This code is thought-out to be run with cli version of php */ 

//Initial Setup
define('APIID',NNNNNNN); //api_id — Application identifier for accessing the Telegram API, which can be obtained at https://my.telegram.org
define('APIHASH','XXXXXXXXX'); //api_hash — Hash of the Application identifier for accessing the Telegram API, which can be obtained at https://my.telegram.org
define('PHONENUMBER','+NNNNNNNNNN'); //my phone number in international format
define('YO',NNNNNNNN); //Telegram ID of my account
define('DBPATH','/var/tdlib/db'); //location of TDLib database
define('DBFILES','/var/tdlib/files'); //Location of TDLib files
$yo=YO; 

//Paths of modules can be different in your instalation, adjust them accordingly.

require ('/opt/tdlib-php-ffi/src/TDLib.php'); //tdlib-php-ffi module

try{
   $client=new Thisismzm\TdlibPhpFfi\TDLib('/opt/td/tdlib/lib/libtdjson.so'); //this is compiled TDlib
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
                  //Receive different parameters of present configuration
                  if (isset($msg['name'])){
                     switch($msg['name']){
                        case 'my_id': $yo=$msg['value']['value']; break; //my account ID, is needed to identify messages sent by me
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
                        and keep the loop running, but in this example my solution is to exit the loop and run again with the validation code passed as a 
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
                  //Information about user received. 
                  if ($msg['user']['id']==$yo){
                     flog(LOG,'updateUser: YO');
                     //soy yo, no hago nada
                  }else{
                     $log='updateUser';
                     $log.=(isset($msg['user']['@type'])?'[@type]:'.$msg['user']['@type']:'');
                     $log.=(isset($msg['user']['id'])?'[id]:'.$msg['user']['id']:'');
                     $log.=(isset($msg['user']['first_name'])?'[first_name]:'.$msg['user']['first_name']:'');
                     $log.=(isset($msg['user']['last_name'])?'[last_name]:'.$msg['user']['last_name']:'');
                     $log.=(isset($msg['user']['username'])?'[username]:'.$msg['user']['username']:'');
                     $log.=(isset($msg['user']['phone_number'])?'[phone_number]:'.$msg['user']['phone_number']:'');
                     flog(LOG,$log);
                     if (isset($msg['user']['phone_number']) and $msg['user']['phone_number']>0){
                        //veo si lo tengo
                        $sql='select * from mid where msisdn=\''.$msg['user']['phone_number'].'\'';
                        if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la lectura de datos',1); }
                        if (mysqli_num_rows($result)>0){
                           //lo tengo, me fijo si tiene los mismos datos
                           $row=mysqli_fetch_assoc($result);
                           mysqli_free_result($result);
                           if ($msg['user']['id']==$row['userid']){
                              //es el mismo no hago nada
                           }else{
                              //es distinto, actualizo la base y fuerzo la búsqueda del chat
                               $sql='update mid set verif=1,userid='.$msg['user']['id'].' where msisdn=\''.$row['msisdn'].'\'';
                              if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                              $client->send($cid,json_encode(['@type'=>'createPrivateChat','user_id'=>$msg['user']['id'],'@extra'=>$row['msisdn']]));
                              flog(LOG,'send createPrivateChat:'.$row['msisdn']);
                              sleep(1);
                           }
                        }else{
                           //no lo tengo, lo agrego a la base y fuerzo búsqueda del chat
                           $sql='insert into mid (msisdn,userid,verif) values (\''.$msg['user']['phone_number'].'\','.$msg['user']['id'].',1)';
                           if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                           $client->send(json_encode($cid,['@type'=>'createPrivateChat','user_id'=>$msg['user']['id'],'@extra'=>$msg['user']['phone_number']]));
                           flog(LOG,'send createPrivateChat:'.$msg['user']['phone_number']);
                           sleep(1);
                        }
                     }elseif(isset($msg['user']['type']['@type']) and $msg['user']['type']['@type']=='userTypeDeleted'){
                        //Se borró el usuario, fuerzo una nueva búsqueda
                        $sql='update mid set verif=0,userid=0,chatid=0 where userid='.$msg['user']['id'];
                        if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',2); }
                     }elseif(isset($msg['user']['phone_number']) and $msg['user']['phone_number']==''){
                        //Usuario con teléfono oculto no hago nada
                     }else{
                        flog(LOG,'NO IDENTIFICADO: '.print_r($msg,true));
                     }
                  }
                  break;
               case 'updateNewMessage':
                  flog(LOG,'updateNewMessage');
                  if ($msg['message']['sender_id']['user_id']!=$yo){
                     //mensaje recibido
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
                                 flog(LOG,print_r($msg,true));
                                 $mensaje='TIPO MENSAJE:'.$msg['message']['content']['@type'];
                              }
                              break;
                           case 'messageContactRegistered':
                              $mensaje='TIPO MENSAJE:'.$msg['message']['content']['@type'];
                              break;
                           default:
                              flog(LOG,print_r($msg,true));
                              $mensaje='TIPO MENSAJE:'.$msg['message']['content']['@type'];
                              break;
                        }
                        $sql='insert into mo (userid,chatid,msgid,mensaje,fchrec) values ('.$msg['message']['sender_id']['user_id'].','.$msg['message']['chat_id'].','.$msg['message']['id'].',\''.mysqli_real_escape_string($idbase,$mensaje).'\',\''.date('Y-m-d H:i:s',$msg['message']['date']).'\')';
                        if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                     }else{
                        flog(LOG,'NO IDENTIFICADO: '.print_r($msg,true));
                     }   
                  }else{
                     //mensaje enviado
                     //Por ahora no hago nada porque no tengo forma de relacionar unívocamente 
                     //flog(LOG,'MENSAJE ENVIADO: '.print_r($msg,true));
                  }
                  break;
               case 'message':
                  flog(LOG,'message');
                  if ($msg['sender_id']['user_id']==$yo){
                     //mensaje enviado
                     $sql='update mt set estado=3,fchenv=\''.date('Y-m-d H:i:s',$msg['date']).'\',msgid='.$msg['id'].' where idmt='.$msg['@extra'];
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  }else{
                     flog(LOG,'NO IDENTIFICADO: '.print_r($msg,true));
                  }
                  break;
               case 'updateMessageSendSucceeded':
                  flog(LOG,'updateMessageSendSucceeded');
                  if ($msg['message']['sender_id']['user_id']==$yo){
                     //mensaje enviado
                     $sql='update mt set estado=4,fchent=\''.date('Y-m-d H:i:s',$msg['message']['date']).'\',msgid='.$msg['message']['id'].',coderr=\'\' where chatid='.$msg['message']['chat_id'].' and msgid='.$msg['old_message_id'];
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  }else{
                     flog(LOG,'NO IDENTIFICADO: '.print_r($msg,true));
                  }
                  break;
               case 'updateMessageSendFailed':
                  flog(LOG,'updateMessageSendFailed');
                  if ($msg['message']['sender_id']['user_id']==$yo){
                     //mensaje enviado
                     $sql='update mt set estado=2,fchpro=\''.date('Y-m-d H:i:s',$msg['message']['date']).'\',msgid='.$msg['message']['id'].',coderr=\''.$msg['error_code'].'-'.$msg['error_message'].'\' where chatid='.$msg['message']['chat_id'].' and msgid='.$msg['old_message_id'];
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  }else{
                     flog(LOG,'NO IDENTIFICADO: '.print_r($msg,true));
                  }
                  break;
               case 'updateChatReadOutbox':
                  flog(LOG,'updateChatReadOutbox');
                  $sql='update mt set estado=5,fchlei=\''.date('Y-m-d H:i:s').'\' where chatid='.$msg['chat_id'].' and msgid='.$msg['last_read_outbox_message_id'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  break;
               case 'chat':
                  flog(LOG,'chat');
                  //flog(LOG,print_r($msg,true));
                  $sql='update mid set chatid='.$msg['id'].',verif=2 where msisdn=\''.$msg['@extra'].'\'';
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  //reactivo los que hayan quedado pendientes
                  $sql='update mt set estado=0 where estado=1 and msisdn=\''.$msg['@extra'].'\'';
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  break;
               case 'importedContacts':
                  flog(LOG,'importedContacts');
                  if ($msg['user_ids'][0]==0){
                     //no existe el contacto, no sigo buscando
                     $sql='update mid set userid=0,chatid=0,verif=2 where msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                     //actualizo todos los MT pendientes
                     $sql='update mt set estado=2,coderr=\'0-No existe contacto\',fchpro=\''.date('Y-m-d H:i:s').'\' where estado=1 and msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  }else{
                     //Actualizo el contacto
                     $sql='update mid set userid='.$msg['user_ids'][0].' where msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                     //Lanzo la búsqueda del chat
                     $client->send($cid,json_encode(['@type'=>'createPrivateChat','user_id'=>$msg['user_ids'][0],'@extra'=>$msg['@extra']]));
                     flog(LOG,'send createPrivateChat');
                     sleep(1);
                  }   
                  break;
               case 'error':
                  if ($msg['code']==429 and substr($msg['message'],0,17)=='Too Many Requests'){
                     flog(LOG,'error: 429-'.$msg['message'].':'.$msg['@extra']);
                     $sql='update mid set userid=0,chatid=0,verif=3 where msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                     //actualizo todos los pendientes
                     $sql='update mt set estado=2,coderr=\'0-Error en contacto\',fchpro=\''.date('Y-m-d H:i:s').'\' where estado=1 and msisdn=\''.$msg['@extra'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  }elseif($msg['code']==400 and $msg['message']=='Chat not found'){
                     flog(LOG,'error: 400-chat not found:'.$msg['@extra']);
                     //El chat ha cambiado, lanzo búsqueda de nuevo chat
                     $sql='select msisdn from mt where idmt='.$msg['@extra'];
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la lectura de datos',1); }
                     $row=mysqli_fetch_assoc($result);
                     mysqli_free_result($result);
                     $sql='update mt set estado=1 where idmt='.$msg['@extra'];
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                     $sql='select userid from mid where msisdn=\''.$row['msisdn'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la lectura de datos',1); }
                     $usu=mysqli_fetch_assoc($result);
                     mysqli_free_result($result);
                     $sql='update mid set verif=1 where msisdn=\''.$row['msisdn'].'\'';
                     if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                     //
                     $client->send($cid,json_encode(['@type'=>'createPrivateChat','user_id'=>$usu['userid'],'@extra'=>$row['msisdn']]));
                     flog(LOG,'send createPrivateChat:'.$row['msisdn']);
                     sleep(1);
                  }else{
                     flog(LOG,'NO IDENTIFICADO: '.print_r($msg,true));
                  }
                  break;
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
                  flog(LOG,$msg['@type']);
                  break;
               default:
                  flog(LOG,'NO IDENTIFICADO: '.print_r($msg,true));
            }
         }else{
            flog(LOG,'NO IDENTIFICADO: '.print_r($msg,true));
         }
      }else{
         //No hay para recibir, me fijo si hay para mandar
         $sql='select mt.idmt,mt.mensaje,mt.msisdn,mid.userid,mid.chatid,mid.verif from mt left join mid on mt.msisdn=mid.msisdn where mt.estado=0 order by mt.idmt limit 1';
         if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la lectura de datos',1); }
         if (mysqli_num_rows($result)==1){
            $row=mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            if (is_null($row['verif'])){
               //Es uno nuevo, no tengo el maestro, lo pongo como pendiente
               $sql='update mt set estado=1,fchpro=\''.date('Y-m-d H:i:s').'\' where idmt='.$row['idmt'];
               if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
               //agrego el registro al maestro
               $sql='insert into mid (msisdn,verif) values (\''.$row['msisdn'].'\',0)';
               if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
            }else{
               if ($row['userid']>0 and $row['chatid']>0){
                  //Tengo usuario y chat, envío el mensaje
                  $sql='update mt set estado=3,fchpro=\''.date('Y-m-d H:i:s').'\',fchenv=\''.date('Y-m-d H:i:s').'\',userid='.$row['userid'].',chatid='.$row['chatid'].' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  $acc=json_encode(['@type'=>'sendMessage','chat_id'=>$row['chatid'],'message_thread_id'=>0,'reply_to_message_id'=>0,'input_message_content'=>array('@type'=>'inputMessageText','text'=>['@type'=>'formatedText','text'=>$row['mensaje']]),'@extra'=>$row['idmt']]);
                  $client->send($cid,$acc);
                  flog(LOG,'send sendMessage:'.$row['idmt']);
                  sleep(1);
               }elseif ($row['verif']==2){
                  //Está verificado y no tiene usuario ni chat, lo pongo como pendiente y fuerzo verificación del contacto
                  $sql='update mt set estado=1,fchpro=\''.date('Y-m-d H:i:s').'\',coderr=\'0-No existe contacto\' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
                  $sql='update mid set verif=0 where msisdn=\''.$row['msisdn'].'\'';
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
               }elseif ($row['verif']==3){
                  //Está verificado en estado 3 (too many requests), lo marco como error
                  $sql='update mt set estado=2,coderr=\'0-Error en contacto\',fchpro=\''.date('Y-m-d H:i:s').'\' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
               }elseif ($row['verif']>3){
                  //Usuario en lista de exclusión u otro motivo
                  $sql='update mt set estado=2,coderr=\'0-Usuario bloqueado\',fchpro=\''.date('Y-m-d H:i:s').'\' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
               }else{
                  //Está en proceso de verificación, lo pongo como pendiente
                  $sql='update mt set estado=1,fchpro=\''.date('Y-m-d H:i:s').'\' where idmt='.$row['idmt'];
                  if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
               }
            }
         }elseif ((time()-$tempo)>$intervalo){
            //veo si hay registros para actualizar
            $sql='select * from mid where verif=0 order by fchver limit 1';
            if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la lectura de datos',1); }
            if (mysqli_num_rows($result)==1){
               $row=mysqli_fetch_assoc($result);
               mysqli_free_result($result);
               $client->send($cid,json_encode(['@type'=>'importContacts','contacts'=>[['@type'=>'contact','phone_number'=>'+'.$row['msisdn'],'user_id'=>0]],'@extra'=>$row['msisdn']]));
               flog(LOG,'send importContacts:'.$row['msisdn']);
               sleep(1);
               $sql='update mid set verif=1 where msisdn=\''.$row['msisdn'].'\'';
               if (!$result=mysqli_query($idbase,$sql)){ throw new Exception ('Error en la escritura de datos',1); }
               $tempo=time();
            }
         }else{
            sleep(1);
         }
      }
   }
}
catch (\Exception $e) {
   flog(LOGERROR,$e->getMessage());
   if (isset($sql)){flog(LOGERROR,$sql);}
   if (isset($acc)){flog(LOGERROR,$acc);}
   if (isset($msg)){flog(LOGERROR,print_r($msg,true));}
}

(this work is in progress)

