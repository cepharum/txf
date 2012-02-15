<?php


namespace de\toxa\txf\datasource;


interface statement
{
	public function execute();
	public function close();
	public function count();
	public function cell();
	public function row();
	public function all();
	public function errorText();
	public function errorCode();
}
