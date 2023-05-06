# tdlib-php
Use Telegram TDLib from PHP

*Esta es mi humilde contribución al uso de Telegram desde PHP, después de haber pasado horas de prueba y error, ante la falta de ejemplos de uso en las páginas oficiales.*

This example is a very simple gateway to send an recieve messages with Telegram. I wrote it because I had the need to send messages programaticaly to a list of users I only know their phone numbers.

To send messages using TDLib to a phone number you have to open a chat with that user (supposed that phone number belongs to a user that has a Telegram account). The steps are these:

-With the phone number you request TDLib to import a contact (import the information of the contact that is registered with that phone number), with the response of TDLib you obtain the userID of that contact.

-With the userID you request TDLib to create a chat with that user. In case you had chatted with that user in the past, TDLib will reopen the chat. In either case it will respond with the chatID.

-With the chatID now you can send the message.

Messages are sent from a Telegram account (the one opened with a phone number), so previous to send an receive messages you need to authenticate. It is needed to have the Telegram application active in the phone (or the web or desktop versions) because a verification code will be sent to it.

Although some request to TDLib could be synchronous, all requests used are asyncronous.

It use 3 MariaDB (MySQL) tables:

mo: this table will store all messages sent to you. 

mt: this table will store all messages you want to send.

mid: this table will store the userID and chatID of all the phone numbers you want to send messages. This table is filled dinamicaly as the program runs.

## Instalation

Install PHP 7.4 or above.

Install MariaDB or MySQL.

Compile TDLib using instruccions from: https://tdlib.github.io/td/build.html

Install (git clone) the library **tdlib-php-ffi** from https://github.com/thisismzm/tdlib-php-ffi 

Install (git clone) this package. 

## How to use it

First you have to create tables with **tables.sql** and configure **example.php**, lines 8 to 13 in the code. 

You get **api_id** and **api_hash** at https://my.telegram.org, and **phone number** is the MSISDN of the line you are going to use for this application. **Account ID** can be configured later. In this instance you can put any numeric value. The exact value will be delivered by TDLib when it sends updates. You can add extra instructions to line 58 of the code to get the value or you can increase verbosity level in line 40.

Run the program on the console in the foreground:
```
php -f example.php
```
This first time the program will request authorization to TDLib, and TDLib will generate a code that will be sent by a Telegram message to the phone number configured (you need a phone with the app running, or the desktop version running) and stop itself. 

Now, run the program with that code:
```
php -f example.php <code>
```
You should receive in the phone or desktop app a second message telling that a new session was opened. At this time, you also got the Account ID. You can stop execution, configure that value and run, this time in the background.
```
nohup php -f example.php >/dev/null 2>&1 &
```
Unless you stop it or an error occurs, the program will run indefinitely. 

You can read mo table to see messages received or write on mt table to send messages.










