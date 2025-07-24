<?php

namespace MediaWiki\Extension\DataMaps\Libraries;

use Shellbox\Command\UnboxedResult;

class GDALException extends \RuntimeException {
	public function __construct( string $cmdName, UnboxedResult $result ) {
		$exitCode = $result->getExitCode();
		$stdout = $result->getStdout();
		$stderr = $result->getStderr();
		$messages = [ "GDAL command $cmdName failed with exit code $exitCode." ];
		if ( $stdout ) {
			$messages[] = "Command out: $stdout";
		}
		if ( $stderr ) {
			$messages[] = "Command err: $stderr";
		}
		parent::__construct( implode( "\n", $messages ) );
	}
}
