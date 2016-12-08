<?php

/**
 * Created by PhpStorm.
 * User: Daniel Eaton
 * Date: 12/7/2016
 * Time: 12:22 AM
 *
 * require all composer dependencies; requiring the autoload file loads all composer packages at once
 **/

require_once(dirname(__DIR__, 2) . "/vendor/autoload.php");
/**
 * require mailer-config.php
 **/
require_once("util/mail-config.php");

// verify user's reCAPTCHA input
$recaptcha = new \Recaptcha\ReCaptcha($secret);

$resp = $recaptcha->verify($_POST["g-recaptcha-response"], $_SERVER["REMOTE_ADDR"]);
echo 'ayr';
try {

	// if reCAPTCHA error, output the error code to the user
	if (!$resp->isSuccess()) {
		throw(new Exception("reCAPTCHA error!"));
		echo 'ayy';
	}

	// sanitize the inputs from the form: name, email, subject, and message
	// this assumes jQuery (not Angular will be submitting the form, so we're using the $_POST superglobal
	$firstName = filter_input(INPUT_POST, "firstName", FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
	$lastName = filter_input(INPUT_POST, "lastName", FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
	$emailAddress = filter_input(INPUT_POST, "emailAddress", FILTER_SANITIZE_EMAIL);
	$telephone = filter_input(INPUT_POST, "telephoneNumber", FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
	$information = filter_input(INPUT_POST, "information", FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

	// create Swift message
	$swiftMessage = Swift_Message::newInstance();

	// attach the sender to the message
	// this takes the form of an associative array where the Email is the key for the real name
	$swiftMessage->setFrom([$email => $name]);

	/**
	 * attach the recipients to the message
	 * $MAIL_RECIPIENTS is set in mail-config.php
	 **/
	$recipients = $MAIL_RECIPIENTS;
	$swiftMessage->setTo($recipients);

	// attach the subject line to the message
	$swiftMessage->setSubject($subject);

	/**
	 * attach the actual message to the message
	 * here, we set two versions of the message: the HTML formatted message and a special filter_var()ed
	 * version of the message that generates a plain text version of the HTML content
	 * notice one tactic used is to display the entire $confirmLink to plain text; this lets users
	 * who aren't viewing HTML content in Emails still access your links
	 **/
	$swiftMessage->setBody($message, "text/html");
	$swiftMessage->addPart(html_entity_decode($message), "text/plain");

	/**
	 * send the Email via SMTP; the SMTP server here is configured to relay everything upstream via CNM
	 * this default may or may not be available on all web hosts; consult their documentation/support for details
	 * SwiftMailer supports many different transport methods; SMTP was chosen because it's the most compatible and has the best error handling
	 * @see http://swiftmailer.org/docs/sending.html Sending Messages - Documentation - SwitftMailer
	 **/
	$smtp = Swift_SmtpTransport::newInstance("localhost", 25);
	$mailer = Swift_Mailer::newInstance($smtp);
	$numSent = $mailer->send($swiftMessage, $failedRecipients);

	/**
	 * the send method returns the number of recipients that accepted the Email
	 * so, if the number attempted is not the number accepted, this is an Exception
	 **/
	if($numSent !== count($recipients)) {
		// the $failedRecipients parameter passed in the send() method now contains contains an array of the Emails that failed
		throw(new RuntimeException("unable to send email"));
	}

	// report a successful send
	echo "<div class=\"alert alert-success\" role=\"alert\">Email successfully sent.</div>";

} catch(Exception $exception) {
	echo "<div class=\"alert alert-danger\" role=\"alert\"><strong>Oh snap!</strong> Unable to send email: " . $exception->getMessage() . "</div>";
}
