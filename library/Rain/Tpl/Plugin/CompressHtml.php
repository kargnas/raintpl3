<?php

namespace Rain\Tpl\Plugin;
require_once __DIR__ . '/../Plugin.php';

class CompressHtml extends \Rain\Tpl\Plugin
{
	protected $hooks = array('beforeParse');

	public function beforeParse(\ArrayAccess $context){
		$html = $context->code;
		$context->code = preg_replace("/^\t*(.*)$/m", "$1", $html );
	}
}
