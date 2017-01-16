<?php

namespace IrfanTOOR;

use IrfanTOOR\Engine\Console as Console;
use IrfanTOOR\Engine\Container as Container;

# Project : phpBuild
# Builds your project from build.json

class phpBuild extends Console
{
	const
		NAME = 'PHP Build',
		VERSION = '0.2',
		DESCRIPTION = 'Build your composer projects using json config';
	
	protected $container;

	public function __construct() {
		parent::__construct(self::NAME, self::VERSION, self::DESCRIPTION);

		# The only and default commmand of this application
		$this->command('build','b|build','e.g --build=build.json',[
			'default' => 1,
			'closure' => function($c) {
				$config = [
					'file' => 'build.json',
					'basedir' => '.',
					'builddir' => './build',
				];

				$c->build($config);
			}
		]);
	}

	public function build($config) {

		# Container
		$container = $this->container = new Container($config);

		$container['processed'] = [];
		$file = $container['file'];

		$this->info('processing file: '. $file);
		if (!file_exists($file)) {
			$this->error("Error: build file not present ...");
			return;
		}

		$buildjson = file_get_contents($file);
		$build = json_decode($buildjson, '__ARRAY__');
		
		if (!is_array($build)) {
			# Vaerify the source json file
			$this->escape(['red']);
			system('composer validate --no-check-all --no-check-lock --no-check-publish '.$file, $return);
			
			$this->error("Error: can not continue ...");
			return;
		}

		$this->container['build'] = $build;
		$this->outln( "name: " . $build['name']);
		$this->outln( "version: " . $build['version']);

		$arg_sections = $this->args['values'];

		# If no section is passed
		if (!count($arg_sections))
			$arg_sections = [ $build['default'] ];

		foreach ($arg_sections as $arg_section) {
			if (isset($build['groups'][$arg_section])) {
				$group = $build['groups'][$arg_section];
				if (is_string($group))
					$group = explode(',', $group);
			}

			elseif (isset($build['sections'][$arg_section])) {
				$group = [$arg_section];
			}
			
			else {
				$this->error('Error: the requested section is not defined in the file');
				return;
			}

			foreach ($group as $section) {
				$this->process($section);
			}
		}
		
		$this->success(PHP_EOL . 'Build Completed ...');
		die();
	}	
	
	public function process($section_name) {
		
		if (!in_array($section_name, ($processed = $this->container['processed']))) {

			$section = $this->container['build']['sections'][$section_name];
			$this->parseArray($section);
			
			$processed[] = $section_name;
			$this->container['processed'] = $processed;
		}
	}
	
	public function parseArray($section) {
		$c = $this->container;

		# Process cette section		
		$fail_on_error = false;
		foreach($section as $cmd => $data) {
			if (is_string($data))
				$data = [$data];				
			
			switch($cmd) {
				case 'fail_on_error':
					$fail_on_error = ($data)? true : false;
					break;
				
				case 'depends':
					foreach($data as $section) {
						$this->process($section);
					}
					break;
				
				case 'echo':
					foreach($data as $text) {
						$this->outln($text, ['magenta', 'bold']);
					}
					break;
				
				case 'system':
					foreach($data as $line) {
						$line = str_replace('${basedir}', $c['build']['basedir'], $line);
						$line = str_replace('${builddir}', $c['build']['builddir'], $line);
						$this->out($line . ' ', 'blue');
						$result = 0;
						
						# execute the system command
						system( $line . ' 1>.stdout.txt 2>.stderror.txt', $result );
						$error = ($result != 0) ? true : false;

						# the command ressult
						if ($error) {
							if ($fail_on_error)
								$this->error('[Error]');    # error but can not continue
							else
								$this->warning('[Error]');  # error but can continue
						} else {
							$this->success('[OK]');         # command executed ok
						}
						
						# output the command output or not
						if ($error && ($this->verbose || $fail_on_error)) {
							$this->escape('red');
							system('cat .stdout.txt');
							system('cat .stderror.txt');								
						}

						# delete the temp files
						system('rm .stdout.txt > /dev/null; rm .stderror.txt > /dev/null');		

						if ($fail_on_error && $result!=0) {
							$this->outln('');
							$this->error('Aborting phpbuild ...');
							die();
						}
					}					
					break;
					
				case 'foreach':
					$list = $data['files'];
					$commands = $data['commands'];
					$exclude = isset($data['exclude'])? $data['exclude'] : [];
					if (is_string($exclude))
						$exclude = [$exclude];
					$files = null;
					foreach($list as $item) {
						$item = str_replace('${basedir}', $c['build']['basedir'], $item);
						$item = str_replace('${builddir}', $c['build']['builddir'], $item);
						
						$cmd = "find $item";
						foreach($exclude as $ex) {
							$cmd .= " | grep -v -e '$ex'";
						}
						ob_start();
						system("$cmd 2>/dev/null");
						$items = ob_get_clean();
						$items = explode(PHP_EOL, $items);
						array_pop($items);
						$files = $files ? array_merge($files, $items) : $items ;
					}
					
					foreach($commands as $k=>$v) {
						if (is_string($v))
							$v = [$v];
											
						foreach($v as $vv) {
							$data = [];
							foreach($files as $file) {
								$data[] = str_replace('${file}', $file, $vv);
							}
							self::parseArray([
								'fail_on_error' => $fail_on_error,
								$k => $data
							]);
						}
					}
					break;
			
				default:						
			}
		}	
	}	
}
