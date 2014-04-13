<?php
/**
 * Copyright (c) 2013-2014, cepharum GmbH, Berlin, http://cepharum.de
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author: Thomas Urban <thomas.urban@cepharum.de>
 * @project: Lebenswissen
 */

namespace de\toxa\txf;


class mail {

	protected $_subject;

	protected $_content = null;

	protected $_mime = 'text/plain';

	protected $_recipients = array();

	protected $_bcc = array();

	const PREG_ADDRESS = '/^(([^+@]+)(?:\+([^@]+))?)@((?:[^.]+\.)+[a-z][a-z]+)$/i';


	/**
	 * Creates mail to be sent.
	 *
	 * @return mail
	 */

	public static function create()
	{
		return new static();
	}

	/**
	 * Tries to detect MIME of provided code.
	 *
	 * This method is currently differing HTML and plain text, only.
	 *
	 * @param string $code content to analyse
	 * @return string MIME type
	 */

	protected static function detectMime( $code )
	{
		if ( preg_match( '#<(\S+)(\s[^>]*)?(>.*?</\1>|/>)#s', $code ) )
			// found HTML tag sequence -> detect HTML
			return 'text/html';

		return 'text/plain';
	}

	/**
	 * Sets subject of mail.
	 *
	 * @param $subject
	 * @return $this
	 */

	public function subject( $subject )
	{
		$this->_subject = trim( $subject );

		return $this;
	}

	/**
	 * Provides content of mail to use.
	 *
	 * @param string $content content/body of mail
	 * @param string $mime MIME type of provided content/body
	 * @param string $encoding charset/encoding of provided content/body
	 * @return $this
	 * @throws \InvalidArgumentException
	 */

	public function content( $content, $mime = 'text/plain', $encoding = 'utf-8' )
	{
		$this->_content = trim( $content );

		$mime = trim( $mime );
		switch ( $mime )
		{
			default :
				if ( $mime !== '' )
					throw new \InvalidArgumentException( 'invalid MIME type' );

				$mime = static::detectMime( $content );
			case 'text/plain' :
			case 'text/html' :
				$this->_mime = $mime . '; charset=' . $encoding;
		}

		return $this;
	}

	/**
	 * Adds another single recipient.
	 *
	 * @param string $recipient mail address to add
	 * @return $this
	 * @throws \InvalidArgumentException on providing invalid mail address
	 */

	public function addRecipient( $recipient )
	{
		if ( !static::isValidAddress( $recipient ) )
			throw new \InvalidArgumentException( 'invalid recipient address: ' . $recipient );

		$this->_recipients[] = $recipient;

		return $this;
	}

	/**
	 * Adds another single recipient to separate list of BCC recipients.
	 *
	 * @param string $recipient mail address to add
	 * @return $this
	 * @throws \InvalidArgumentException on providing invalid mail address
	 */

	public function addBcc( $recipient )
	{
		if ( !static::isValidAddress( $recipient ) )
			throw new \InvalidArgumentException( 'invalid recipient address: ' . $recipient );

		$this->_bcc[] = $recipient;

		return $this;
	}

	/**
	 * Retrieves configured sender to use on sending this mail.
	 *
	 * @return string
	 * @throws \InvalidArgumentException on missing or invalid sender address
	 */

	protected function getSender()
	{
		$sender = config::get( 'mail.sender' );

		if ( !static::isValidAddress( $sender ) )
			throw new \InvalidArgumentException( 'invalid/missing mail sender address' );

		return $sender;
	}

	/**
	 * Retrieves optionally configured address to use on replying to sent mail.
	 *
	 * @return string mail address to use for replying, empty string if unset
	 * @throws \InvalidArgumentException on invalid reply-to address
	 */

	protected function getReplyTo()
	{
		$replyTo = trim( config::get( 'mail.reply-to' ) );

		if ( $replyTo !== '' && !static::isValidAddress( $replyTo ) )
			throw new \InvalidArgumentException( 'invalid/missing mail sender address' );

		return $replyTo;
	}

	/**
	 * Detects if provided string contains valid e-mail address or not.
	 *
	 * @param $address
	 * @return bool
	 */

	public static function isValidAddress( $address )
	{
		return !!preg_match( self::PREG_ADDRESS, trim( $address ) );
	}

	/**
	 * Tests if some required information on current mail is missing or not.
	 *
	 * @return void
	 * @throws \InvalidArgumentException if some information is missing or invalid
	 */

	protected function check()
	{
		if ( $this->_subject === '' )
			throw new \InvalidArgumentException( 'missing subject' );

		if ( $this->_content === '' )
			throw new \InvalidArgumentException( 'missing content' );

		if ( $this->_mime === '' )
			throw new \InvalidArgumentException( 'missing MIME' );

		if ( !count( $this->_recipients ) )
			throw new \InvalidArgumentException( 'missing recipient' );
	}

	/**
	 * Send prepared mail.
	 *
	 * This method is returning result from invoking mail(), thus returning true
	 * does not actually implies mail having sent to recipient for it might be
	 * rejected by local or intermediate mail transfer agents.
	 *
	 * @return bool true on successfully sending mail, false otherwise
	 */

	public function send()
	{
		$this->check();


		/*
		 * compile additional mail headers
		 */

		$headers  = 'From: ' . $this->getSender() . "\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: {$this->_mime}\r\n";

		$replyTo = $this->getReplyTo();
		if ( $replyTo !== '' )
			$headers .= "Reply-To: $replyTo\r\n";

		if ( count( $this->_bcc ) )
			$headers .= 'Bcc: ' . implode( ', ', $this->_bcc ) . "\r\n";


		/*
		 * prepare basic mail parameters
		 */

		$recipients = implode( ', ', $this->_recipients );
		$subject    = $this->_subject;
		$content    = $this->_content;

		$subject = '=?UTF-8?B?' . base64_encode( $subject ) . '?=';


		/*
		 * send mail
		 */

		return !!mail( $recipients, $subject, $content, $headers );
	}

	/**
	 * Sets content of mail by rendering selected template.
	 *
	 * Rendered template might start with subject of mail to use separated by
	 * single pipe | from actual content/body of mail, optionally.
	 *
	 * @param string $template name of template to render
	 * @param array $templateData data to provide on rendering template
	 * @return $this
	 */

	public function usingTemplate( $template, $templateData )
	{
		$content = view::render( $template, $templateData );

		$split = strpos( $content, '|' );
		if ( $split > 0 ) {
			$this->subject( substr( $content, 0, $split ) );
			$content = substr( $content, $split + 1 );
		}

		return $this->content( $content, static::detectMime( $content ) );
	}
}
