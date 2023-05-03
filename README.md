# tdlib-php
Use Telegram TDLib from PHP

*Esta es mi humilde contribución al uso de Telegram desde PHP, después de haber pasado horas de prueba y error, ante la falta de ejemplos de uso.*

This example is a very simple gateway to send an recieve messages with Telegram. I wrote it because I had the need to send messages programaticaly to a list of users I only know their phone numbers.

To send messages using TDLib to a phone number you have to open a chat with that user (supposed that phone number belongs to a user that has a Telegram account). The steps are these:

-With the phone number you request TDLib to import a contact (import the information of the contact that is registered with that phone number), with the response of TDLib you obtain the userID of that contact.

-With the userID you request TDLib to create a chat with that user. In case you had chatted with that user in the past, TDLib will reopen the chat. In either case it will respond with the chatID.

-With the chatID now you can send the message.

Messages are sent from my Telegram account (the one I opened with my phone number), so previous to send an receive messages I need to authenticate. It is needed to have the Telegram application active in the phone (or the web or desktop versions) because a verification code will be sent to it.

Although some request to TDLib could be synchronous, I used all asyncronous requests.

I use 3 MariaDB (MySQL) tables:

mo: this table will store all messages sent to me. 

mt: this table will store all messages I want to send.

mid: this table will store the userID and chatID of all the phone numbers I want to send messages. This table is filled dinamicaly as the program runs.

To use the code example showed here you need:

Compile TDLib using instruccions from: https://tdlib.github.io/td/build.html

Install (git clone) the library **tdlib-php-ffi** from https://github.com/thisismzm/tdlib-php-ffi 

