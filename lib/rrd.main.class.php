<?php
namespace rrd {

	require_once __DIR__ . DIRECTORY_SEPARATOR . 'psy' . DIRECTORY_SEPARATOR . 'psy.php';
	require_once 'util.php';
	use Exception, SimpleXMLElement, ShuntingYard;

	class main {
		protected $cfg; // a SimpleXMLElement configuration object
		protected $rrdtool; // a rrd\rrdtool object
		protected $connectors = array();
		protected $extractors = array();
		protected $vars;
		protected $cmd;

		function __construct($opts) {
			$this->build_config($opts['config'], $opts['template']);
			// fix paths
			foreach(array('db', 'img') as $path) {
				$this->cfg->rrd->paths->$path = absolute_path((string) $this->cfg->rrd->paths->$path, $opts['paths'][$path]);
				if (!is_dir((string) $this->cfg->rrd->paths->$path)) {
					// create a path if not exsits
					echo 'creating ' . $this->cfg->rrd->paths->$path . PHP_EOL;
					make_dir((string) $this->cfg->rrd->paths->$path);
				}
			}
			$this->rrdtool = new rrdtool($this->cfg->rrd);
//			echo $this->cfg->asXML() . PHP_EOL;
//			print_r($this->cfg);
//			exit;
		}

		function build_config($cfg, $default) {
			// XMLify cfg if needed
			if (($cfg = $this->xmlify($cfg)) === FALSE) {
				throw new Exception('Invalid configuration suplied!');
			}

			// process default config if any
			if (!empty($default)) {
				// XMLify default config
				if (($this->cfg = $this->xmlify($default)) === FALSE) {
					throw new Exception('Invalid default configuration suplied!');
				} else {
					// merge default with custom config
					$this->xml_merge_recursive($this->cfg, $cfg);
				}
			} else {
				// no default config, use only the custom one
				$this->cfg = $cfg;
			}
		}

		function xmlify($xml) {
			if (is_string($xml)) {
				// string parameter. ehther file or xml
				if (file_exists($xml)) {
					// file
					return simplexml_load_file($xml);
				} else {
					// xml
					return simplexml_load_string($xml);
				}
			} elseif (is_object($xml) and (get_class($xml) === 'SimpleXMLElement')) {
				// ready object
				return $cfg;
			} else {
				return FALSE;
				throw new Exception('Invalid XML suplied!');
			}
		}
		function fetch_all() {
			foreach($this->cfg->xpath('devices/device') as $device) {
				$this->fetch_device($device);
			}
		}

		function fetch_device($device) {
			foreach($device->xpath('sensors/sensor') as $sensor) {
				$this->fetch_sensor($device, $sensor);
			}
		}

		function fetch_sensor($device, $sensor) {
			$filename = (string) $device->attributes()->id . '.' . preg_replace('/:/', '.', $sensor->attributes()->id) . '.rrd';
			echo  "\t\t{$filename}\n";
			$extractor = $this->extractor(
				$this->connector(
					$id = (string) $sensor->attributes()->connector,
					$device->xpath("connectors/connector[@id='{$id}']")
				),
				$id = (string) $sensor->attributes()->extractor,
				$device->xpath("extractors/extractor[@id='{$id}']"),
				new SimpleXMLElement("<extractor device='{$device->attributes()->id}' />") // add unique device id attribute in order to make the extractor persistent for this device only
			);
			$data = $extractor->get($sensor);
			if ($data === FALSE) {
				echo "\t\t\tExtractor failed. Skipping\n";
			} else {
				$this->rrdtool->update($filename, $data, (string) $sensor->attributes()->schema);
			}
		}

		function connector() {
			if (!func_num_args()) {
				throw new Exception(__METHOD__ . ' called without paramters');
			}
			$id = func_get_arg(0);
			$cfg = $this->xml_merge(array_merge($this->cfg->xpath("connectors/connector[@id='{$id}']"), array_slice(func_get_args(), 1)));
			if (is_null($cfg) or ((string) $cfg->attributes()->class === '')) {
				throw new Exception("connector id `{$id}` not found!");
			}
			$hash = md5($cfg->asXML(), TRUE);
			if (!array_key_exists($hash, $this->connectors)) {
				$class = __NAMESPACE__ . '\\connectors\\' . (string) $cfg->attributes()->class;
//				echo "new {$class}\n";
				$this->connectors[$hash] = new $class($cfg);
			}
			return $this->connectors[$hash];
		}

		function extractor() {
			if (func_num_args() < 2) {
				throw new Exception(__METHOD__ . ' called without enough paramters');
			}

			$connector = func_get_arg(0);
			$id = func_get_arg(1);
			$cfg = $this->xml_merge(array_merge($this->cfg->xpath("extractors/extractor[@id='{$id}']"), array_slice(func_get_args(), 2)));
			if (is_null($cfg)) {
				throw new Exception("extractor id `{$id}` not found!");
			} elseif ($cfg->attributes()->persistent == 1) {
				// TODO: add unique connector id
				$hash = md5($cfg->asXML(), TRUE);
				if (!array_key_exists($hash, $this->extractors)) {
					$class = __NAMESPACE__ . '\\extractors\\' . (string) $cfg->attributes()->class;
//					echo "new {$class}\n";
					$this->extractors[$hash] = new $class($connector, $cfg);
				}
				$extractor = $this->extractors[$hash];
			} else {
				$class = __NAMESPACE__ . '\\extractors\\' . (string) $cfg->attributes()->class;
//				echo "new {$class}\n";
				$extractor = new $class($connector, $cfg);
			}
			return $extractor;
		}

		function draw_all() {
			foreach($this->cfg->xpath('graphs/graph') as $graph) {
				$this->draw_graph($graph);
			}
		}

		function draw_graph($graph) {
			$this->cmd = array();
			$this->vars = array();
			$this->build_defs($graph->data);
			$this->build_graph($graph->series, $graph->legend);

			// customizations
			if (!empty($graph->settings)) {
				static $options = array('legend-direction', 'width', 'height', 'vertical-label', 'only-graph', 'full-size-mode', 'font');
				foreach($graph->settings->children() as $opt) {
					$opt_name = $opt->getName();
					if (in_array($opt_name, $options)) {
						$value = (string) $opt;
						if ($value === '') {
							$this->cmd[] = "--{$opt_name}";
						} elseif (preg_match('/^\d+$/', $value)) {
							$this->cmd[] = "--{$opt_name}={$value}";
						} elseif (preg_match('/:/', $value)) {
							$this->cmd[] = "--{$opt_name} {$value}";
						} else {
							$this->cmd[] = "--{$opt_name}=\"{$value}\"";
						}
					}
				}
			}

			// put all the rest
			$this->cmd = array_merge(array(NULL), $this->cmd, array(
				'COMMENT:\s',
//				'--alt-y-grid',
				'--watermark "LiquidGrapher v0.1 beta @ ' . date('D, d M Y H:i:s T') . '"',
				'--imgformat PNG',
				'--no-gridfit',
				'--legend-position=south',
				'--slope-mode',
				'--lower-limit 0',
				'--alt-autoscale-max',
				'--font DEFAULT:0:Courier',
//				'--font DEFAULT:7:',
//				'--font TITLE:12:',
				'--disable-rrdtool-tag',
				NULL,
				NULL,
			));
			static $periods = array('d' => 'last 24 hours', 'w' => 'last week', 'm' => 'last month', 'y' => 'last year');
			foreach(array_keys($periods) as $period) {
//			foreach(array('d') as $period) {
				$filename = absolute_path($this->cfg->rrd->paths->img . DIRECTORY_SEPARATOR . $graph->attributes()->id . '.' . $period . '.png', @$this->opts['base_path']['img']);
				echo "\t\t{$graph->attributes()->id}.{$period}.png\n";
				$this->cmd[0] = "graph {$filename}";
				$this->cmd[count($this->cmd) - 2] = "-t \"{$graph->settings->title}\""; // ({$periods[$period]})\"";
				$this->cmd[count($this->cmd) - 1] = "-s -1{$period}";
//				print_r($this->cmd);
				$this->rrdtool->exec(join(' ', $this->cmd));
			}
//			print_r($this->cmd); exit;
		}

		function build_defs($xml) {
			static $var_prefix = 'var';
			$var_index = 0;

			foreach($xml->var as $rec) {
				$row = array();
				$var = (string) $rec->attributes()->id;
				$sy = new ShuntingYard((string) $rec);

				$token = $sy->first();
				while ($token !== FALSE) {
					if ($token->type === T_DEF) {
						$var_key = $token->value;
						if (isset($this->vars[$var_key])) {
							$row[] = $this->vars[$var_key];
						} elseif (($tmp = preg_split('/:/', $var_key)) and (array_shift($tmp) === 'rrd')) {
							if (!in_array(strtoupper(end($tmp)), array('AVERAGE', 'MIN', 'MAX', 'LAST'))) {
								$cf = 'AVERAGE';
							} else {
								$cf = strtoupper(array_pop($tmp));
							}
							$ds = array_pop($tmp);
							$filename = absolute_path($this->cfg->rrd->paths->db . DIRECTORY_SEPARATOR . join('.', $tmp) . '.rrd', @$this->opts['base_path']['db']);
							$var_value = "{$filename}:{$ds}:{$cf}";
							$var_name = $var_prefix . $var_index;
							$var_index++;
							$this->vars[$var_key] = $var_name;
							$this->cmd[] = "DEF:{$var_name}={$var_value}";
							$row[] = $var_name;
						} else {
							die("Undefined variable `{$var_key}`\n");
						}
					} elseif ($token->type === T_UNARY_MINUS) {
						// multiply by minus one in order to reverse the sign
						$row[] = -1;
						$row[] = '*';
					} elseif ($token->type === T_UNARY_PLUS) {
						// simple skip it
					} else {
						$row[] = $token->value;
					}
					$token = $sy->next();
				}

				$var_key = $var;
				if (isset($this->vars[$var_key])) {
					die("Duplicate variable definition for`{$var}`");
				} elseif ((count($row) === 1) and (strpos($row[0], $var_prefix) === 0)) {
					// simple variable, duplicate key
					$this->vars[$var_key] = $row[0];
				} else {
					$var_name = $var_prefix . $var_index;
					$var_index++;
					$this->vars[$var_key] = $var_name;
					$cdef = 'CDEF:' . $var_name . '=' . join(',', $row);
					$this->cmd[] = $cdef;
				}
			}
		}

		function build_graph($xml, &$legend) {
			$title_width = $legend->widths->title ? (string) $legend->widths->title : 20;
			$col_title_len = $legend->widths->rows ? (string) $legend->widths->rows : 8;
			$spacer = str_repeat(' ', $legend->widths->spacing ? (string) $legend->widths->spacing : 5);

			switch($xml->getName()) {
				case 'series':
					$orientation = empty($xml->attributes()->orientation) ? 'horizontal' : (string) $xml->attributes()->orientation;
					foreach($xml->children() as $child) {
						$this->build_graph($child, $legend, $this->cmd);
					}
					break;
				case 'comment':
					$comment = (string) $xml;
					$this->cmd[] = 'COMMENT:"' . ($comment !== '' ? $comment : ' ') . '"\l';
					break;
				case 'section':
					$this->cmd[] = 'COMMENT:\s';
					$title = 'COMMENT:"' . str_pad($xml->attributes()->title, $title_width);
					$border = 'COMMENT:"' . str_repeat('=', $title_width);
					foreach ($legend->totals->cf as $total) {
						$title .= $spacer . str_pad($total, $col_title_len, ' ', STR_PAD_LEFT);
						$border .= $spacer . str_repeat('=', $col_title_len);
					}
					$this->cmd[] =  $title . '\l"';
	//				$this->cmd[] =  $border . '\l"';
					foreach($xml->children() as $child) {
						$this->build_graph($child, $legend);
					}
					break;
				case 'serie':
					static $default_options = array(
						'var'	=> NULL,
						'type'	=> 'LINE',
						'color'	=> '#000000',
						'sep'	=> NULL,
					);

					$title = (string) $xml[0];
					$opts = array();
					foreach($default_options as $k => $v) {
						$$k = empty($xml->attributes()->$k) ? $v : (string)$xml[0]->attributes()->$k;
					}

					if (isset($sep)) {
						$this->cmd[] = 'COMMENT:"' . str_repeat($sep, $title_width) . str_repeat($spacer . str_repeat($sep, $col_title_len), count($legend->totals->cf)) . '\l"';
					}

					if (!isset($this->vars[$var])) {
						die("There's no `$var`\n");
					}
					$var = $this->vars[$var];
					if ($type === 'STACK') {
						$this->cmd[] = "AREA:{$var}{$color}:\"" . str_pad($title, $title_width - 4 /* color icon takes two chars */) . '":STACK';
					} elseif (in_array($type, array('HRULE', 'VRULE'))) {
						$this->cmd[] = "{$type}:{$var}{$color}" . (empty($title) ? '' : "$title:\"" . str_pad($title, $title_width - 4 /* color icon takes two chars */));
					} else {
						$this->cmd[] = "{$type}:{$var}{$color}:\"" . str_pad($title, $title_width - 4 /* color icon takes two chars */) . '"';
					}

					$abs = 'abs_' . $var;
					if (!isset($this->vars[$abs])) {
						$this->cmd[] = "CDEF:{$abs}=0,$var,GT,$var,1,*,$var,IF";
						$this->vars[$abs] = $abs;
						$skip_vdef = FALSE;
					} else {
						$skip_vdef = TRUE;
					}

					foreach($legend->totals->cf as $total) {
						$cf = (string) $total->attributes()->type;
						$format = (string) $total->attributes()->format;
						$label = str_replace(' ', '_', (string) $total);
						$vdef = strtolower("{$label}_{$var}");
						if (!$skip_vdef) {
							if (strpos($cf, 'PERCENT') === FALSE) {
								$this->cmd[] = "VDEF:{$vdef}={$abs},{$cf}";
							} else {
								@list($cf, $percentile) = explode(':', $cf);
								if (empty($percentile)) {
									$percentile = 95;
								}
								$this->cmd[] = "VDEF:{$vdef}={$abs},{$percentile},{$cf}";
							}
						}
						$this->cmd[] = 'COMMENT:"' . $spacer . '"';
						$this->cmd[] = 'GPRINT:' . $vdef . ':"' . $format . '\g"';
					}
					$this->cmd[] = 'COMMENT:" \l"';
					break;
			}
		}

		function xml_merge() {
			$new = NULL;
			foreach(func_get_args() as $old) {
				if (is_array($old)) {
					$old = call_user_func_array(array($this, 'xml_merge'), $old);
				}
				if (empty($old)) {
					continue;
				}
				if (!isset($new)) {
					$new = new SimpleXMLElement($old->asXML());
				} else {
					foreach($old->attributes() as $k => $v) {
						if (!isset($new->attributes()->$k)) {
							$new->addAttribute($k, $v);
						}
					}
					foreach($old->children() as $k => $v) {
						$new->addChild($k, $v);
					}
				}
			}
			return $new;
		}

		function xml_merge_recursive() {
			$base = NULL;
			foreach(func_get_args() as $arg) {
				if (is_array($arg)) {
					$new = call_user_func_array(array($this, 'xml_merge_recursive'), $arg);
				} elseif (get_class($arg) === 'SimpleXMLElement') {
					$new = $arg;
				} else {
					die('Unsupported argument type `' . gettype($arg) . '`' . PHP_EOL);
				}

				// first object in line. set as master and continue to iterate.
				if (!isset($base)) {
					$base = $new;
					continue;
				}

				// next object.

				// copy attributes
				foreach($new->attributes() as $k => $v) {
					if (!$base->attributes()->$k) {
						$base->addAttribute($k, $v);
					}
				}

				// merge children
				foreach($new->children() as $child) {
					$tag = $child->getName();
					$id = (string) $child->attributes()->id;
					if (!$base->$tag) {
						$base_element = $base->addChild($tag, (string) $child);
					} elseif (!empty($id)) {
						if (($base_element = $base->xpath("{$tag}[@id='${id}']")) and !empty($base_element)) {
							$base_element = $base_element[0];
						} else {
							$base_element = $base->addChild($tag, (string) $child);
						}
					} else {
						$base_element = $base->$tag;
					}
					$this->xml_merge_recursive($base_element, $child);
				}
			}
			return $base;
		}
	}
}
?>
