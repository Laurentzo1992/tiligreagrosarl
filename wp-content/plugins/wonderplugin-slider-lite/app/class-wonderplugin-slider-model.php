<?php 

if ( ! defined( 'ABSPATH' ) )
	exit;
	
require_once 'wonderplugin-slider-functions.php';

class WonderPlugin_Slider_Model {

	private $controller;
	
	function __construct($controller) {
		
		$this->controller = $controller;

		$this->multilingual = false;

		if ( get_option( 'wonderplugin_slider_supportmultilingual', 1 ) == 1 )
		{
			$defaultlang = apply_filters( 'wpml_default_language', NULL);
			if ( !empty($defaultlang) )
			{
				$this->multilingual = true;
				$this->multilingualsys = "wpml";
				$this->defaultlang = $defaultlang;
				$this->currentlang = apply_filters('wpml_current_language', NULL );
			}
		}
	}
	
	function get_upload_path() {
		
		$uploads = wp_upload_dir();
		return $uploads['basedir'] . '/wonderplugin-slider/';
	}
	
	function get_upload_url() {
	
		$uploads = wp_upload_dir();
		return $uploads['baseurl'] . '/wonderplugin-slider/';
	}
	
	function xml_cdata( $str ) {
		
		if ( ! seems_utf8( $str ) ) {
			$str = utf8_encode( $str );
		}

		$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';
	
		return $str;
	}
	
	function replace_data($replace_list, $data)
	{
		foreach($replace_list as $replace)
		{
			$data = str_replace($replace['search'], $replace['replace'], $data);
		}
		
		return $data;
	}
	
	function search_replace_sliders($post)
	{
		$allsliders = sanitize_text_field($_POST['allsliders']);
		$sliderid = sanitize_text_field($_POST['sliderid']);
		
		$replace_list = array();
		for ($i = 0; ; $i++)
		{
			if (empty($post['standalonesearch' . $i]) || empty($post['standalonereplace' . $i]))
				break;
				
			$replace_list[] = array(
						'search' => str_replace('/', '\\/', sanitize_text_field($post['standalonesearch' . $i])),
						'replace' => str_replace('/', '\\/', sanitize_text_field($post['standalonereplace' . $i]))
					);
		}
		
		global $wpdb;
		
		if (!$this->is_db_table_exists())
			$this->create_db_table();
		
		$table_name = $wpdb->prefix . "wonderplugin_slider";
		
		$total = 0;
		
		foreach($replace_list as $replace)
		{
			$search = $replace['search'];
			$replace = $replace['replace'];
			
			if ($allsliders)
			{
				$ret = $wpdb->query( $wpdb->prepare(
						"UPDATE $table_name SET data = REPLACE(data, %s, %s) WHERE INSTR(data, %s) > 0",
						$search,
						$replace,
						$search
				));
			}
			else
			{
				$ret = $wpdb->query( $wpdb->prepare(
						"UPDATE $table_name SET data = REPLACE(data, %s, %s) WHERE INSTR(data, %s) > 0 AND id = %d",
						$search,
						$replace,
						$search,
						$sliderid
				));
			}
			
			if ($ret > $total)
				$total = $ret;
		}
		
		if (!$total)
		{
			return array(
					'success' => false,
					'message' => 'No slider modified' .  (isset($wpdb->lasterror) ? $wpdb->lasterror : '')
			);
		}
		
		return array(
				'success' => true,
				'message' => sprintf( _n( '%s slider', '%s sliders', $total), $total) . ' modified'
			);
	}
	
	function import_sliders($post, $files)
	{				
		if (!isset($files['importxml']))
		{
			return array(
					'success' => false,
					'message' => 'No file or invalid file sent.'
					);
		}
		
		if (!empty($files['importxml']['error']))
		{
			$message = 'XML file error.';
			
			switch ($files['importxml']['error']) {
				case UPLOAD_ERR_NO_FILE:
					$message = 'No file sent.';
					break;
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$message = 'Exceeded filesize limit.';
					break;
			}
			
			return array(
					'success' => false,
					'message' => $message
			);
		}
		
		if ($files['importxml']['type'] != 'text/xml')
		{
			return array(
					'success' => false,
					'message' => 'Not an xml file'
			);
		}
		
		add_filter( 'wp_check_filetype_and_ext', 'wonderplugin_slider_wp_check_filetype_and_ext', 10, 4);
		
		$xmlfile = wp_handle_upload($files['importxml'], array(
				'test_form' => false,
				'mimes' => array('xml' => 'text/xml')
		));

		remove_filter( 'wp_check_filetype_and_ext', 'wonderplugin_slider_wp_check_filetype_and_ext');
		
		if ( empty($xmlfile) || !empty( $xmlfile['error'] ) ) {
			return array(
					'success' => false,
					'message' => (!empty($xmlfile) && !empty( $xmlfile['error'] )) ? $xmlfile['error']: 'Invalid xml file'
			);
		} 
		
		$content = file_get_contents($xmlfile['file']);
		
		$xmlparser = xml_parser_create();
		xml_parse_into_struct($xmlparser, $content, $values, $index);
		xml_parser_free($xmlparser);
		
		if (empty($index) || empty($index['WONDERPLUGINSLIDER']) || empty($index['ID']))
		{
			return array(
					'success' => false,
					'message' => 'Not an exported xml file'
			);
		}

		$keepid = (!empty($post['keepid'])) ? true : false;
		$authorid = sanitize_text_field($post['authorid']);
		
		$replace_list = array();
		for ($i = 0; ; $i++)
		{
			if (empty($post['olddomain' . $i]) || empty($post['newdomain' . $i]))
				break;
			
			$replace_list[] = array(
					'search' => str_replace('/', '\\/', sanitize_text_field($post['olddomain' . $i])),
					'replace' => str_replace('/', '\\/', sanitize_text_field($post['newdomain' . $i]))
					);
		}
				
		$sliders = Array();
		foreach($index['ID'] as $key => $val)
		{
			$sliders[] = Array(
					'id' => ($keepid ? $values[$index['ID'][$key]]['value'] : 0),
					'name' => $values[$index['NAME'][$key]]['value'],
					'data' => $this->replace_data($replace_list, $values[$index['DATA'][$key]]['value']),
					'time' => $values[$index['TIME'][$key]]['value'],
					'authorid' => $authorid
					);
		}

		if (empty($sliders))
		{
			return array(
					'success' => false,
					'message' => 'No slider found'
			);
		}
		
		global $wpdb;
		
		if (!$this->is_db_table_exists())
			$this->create_db_table();
		
		$table_name = $wpdb->prefix . "wonderplugin_slider";
		
		$total = 0;
		foreach($sliders as $slider)
		{	
			$ret = $wpdb->query($wpdb->prepare(
					"
					INSERT INTO $table_name (id, name, data, time, authorid)
					VALUES (%d, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE
					name=%s, data=%s, time=%s, authorid=%s 
					",
					$slider['id'], $slider['name'], $slider['data'], $slider['time'], $slider['authorid'], 
					$slider['name'], $slider['data'], $slider['time'], $slider['authorid']
			));
						
			if ($ret)
				$total++;
		}
		
		if (!$total)
		{
			return array(
					'success' => false,
					'message' => 'No slider imported' .  (isset($wpdb->lasterror) ? $wpdb->lasterror : '')
			);
		}
		
		return array(
				'success' => true,
				'message' => sprintf( _n( '%s slider', '%s sliders', $total), $total) . ' imported'
			);
	}
	
	function export_sliders()
	{
		if ( !check_admin_referer('wonderplugin-slider', 'wonderplugin-slider-export') || !isset($_POST['allsliders']) || !isset($_POST['sliderid']) || !is_numeric($_POST['sliderid']) )
			exit;
		
		$allsliders = sanitize_text_field($_POST['allsliders']);
		$sliderid = sanitize_text_field($_POST['sliderid']);
		
		if ($allsliders)
			$data = $this->get_list_data(true);
		else
			$data = array($this->get_list_item_data($sliderid));
				
		header('Content-Description: File Transfer');
		header("Content-Disposition: attachment; filename=wonderplugin_slider_export.xml");
		header('Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true);
		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Pragma: no-cache");
		header("Expires: 0");
		$output = fopen("php://output", "w");
		
		echo '<?xml version="1.0" encoding="' . get_bloginfo('charset') . "\" ?>\n";
		echo "<WONDERPLUGINSLIDER>\r\n";
		foreach($data as $row)
		{
			if (empty($row))
				continue;
			
			echo "<ID>" . intval($row["id"]) . "</ID>\r\n";
			echo "<NAME>" . $this->xml_cdata($row["name"]) . "</NAME>\r\n";
			echo "<DATA>" . $this->xml_cdata($row["data"]) . "</DATA>\r\n";
			echo "<TIME>" . $this->xml_cdata($row["time"]) . "</TIME>\r\n";
			echo "<AUTHORID>" . $this->xml_cdata($row["authorid"]) . "</AUTHORID>\r\n";
		}
		echo '</WONDERPLUGINSLIDER>';
		
		fclose($output);
		exit;
	}
	
	function eacape_html_quotes($str) {
	
		$result = str_replace("<", "&lt;", $str);
		$result = str_replace('>', '&gt;', $result);
		$result = str_replace("\'", "&#39;", $result);
		$result = str_replace('\"', '&quot;', $result);
		$result = str_replace("'", "&#39;", $result);
		$result = str_replace('"', '&quot;', $result);
		return $result;
	}
	
	function get_multilingual_slide_text($slide, $attr, $currentlang) {

		$result = !empty($slide->{$attr}) ? $slide->{$attr} : '';

		if ($this->multilingual && !empty($slide->langs) )		
		{
			$langs = json_decode($slide->langs, true);
			if ( !empty($langs) && array_key_exists($currentlang, $langs) && array_key_exists($attr, $langs[$currentlang]))
			{
				$result = $langs[$currentlang][$attr];
			}
		}

		return $result;
	}

	function escape_css_for_js($str) {

		$str = str_replace('\\', '\\\\', $str);
		return str_replace('"', '\"', $str);
	}

	function generate_body_code($id, $has_wrapper, $atts) {
		
		global $wpdb;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
		
		if ( !$this->is_db_table_exists() )
		{
			return '<p>The specified slider does not exist.</p>';
		}
		
		$ret = "";
		$item_row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
		if ($item_row != null)
		{
			add_filter('safe_style_css', 'wonderplugin_slider_css_allow');
			add_filter('wp_kses_allowed_html', 'wonderplugin_slider_tags_allow', 'post');
				
			$data = json_decode(trim($item_row->data));
			
			if ( isset($data->publish_status) && ($data->publish_status === 0) )
			{
				return '<p>The specified slider is trashed.</p>';
			}
			
			$cssjs = '';
			$removeinlinecss = !(isset($data->removeinlinecss) && strtolower($data->removeinlinecss) === 'false');

			foreach($data as $datakey => &$value)
			{
				if ($datakey == 'customjs')
					continue;
				
				if ( is_string($value) )
					$value = wp_kses_post($value);
			}
			
			if ($has_wrapper && isset($data->fullbrowserwidth) && strtolower($data->fullbrowserwidth) === 'true')
			{
				$data->fullbrowserwidth = 'false';
				$data->fullwidth = 'true';
			}

			$initOptions = array('outputtext');
			foreach ( $initOptions as $key )
			{
				$data->{$key} = (isset($data->{$key}) && strtolower($data->{$key}) === 'true');
			}

			if (isset($data->customcss) && strlen($data->customcss) > 0)
			{
				$customcss = str_replace("\r", " ", $data->customcss);
				$customcss = str_replace("\n", " ", $customcss);
				$customcss = str_replace("SLIDERID", $id, $customcss);
				if ($removeinlinecss)
				{
					$cssjs .= 'wonderslider_' . $id . '_appendcss("' . $this->escape_css_for_js($customcss) . '");';
				}
				else
				{
					$ret .= '<style>' . $customcss . '</style>';
				}				
			}

			if (isset($data->skincss) && strlen($data->skincss) > 0)
			{
				$skincss = str_replace("\r", " ", $data->skincss);
				$skincss = str_replace("\n", " ", $skincss);
				$skincss = str_replace("SLIDERID", $id, $skincss);
				if ($removeinlinecss)
				{
					$cssjs .= 'wonderslider_' . $id . '_appendcss("' . $this->escape_css_for_js($skincss) . '");';
				}
				else
				{
					$ret .= '<style>' . $skincss . '</style>';
				}	
			}

			$ret .= '<div class="wonderpluginslider-container';

			if (isset($atts['alignment']))
			{
				$ret .= ' wonderpluginslider-align' . $atts['alignment'];
			}	

			$ret .= '" id="wonderpluginslider-container-' . $id . '"'; 
			
			if (isset($data->disableinlinecss) && strtolower($data->disableinlinecss) === 'true')
			{
				$ret .= '>';
			}
			else
			{
				$ret .= ' style="';

				if ( (isset($data->fullbrowserwidth) && strtolower($data->fullbrowserwidth) === 'true')
					|| (isset($atts['alignment']) && $atts['alignment'] == 'full') )
				{
					$ret .= $has_wrapper ? 'max-width:100%;margin:0 auto 180px;' : 'width:auto;max-width:none;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);';

					if (isset($data->isfullscreen) && strtolower($data->isfullscreen) === 'true')
					{
						$ret .= $has_wrapper ? 'height:500px;' : 'height:100%;';
					}
				}
				else
				{
					if ( (isset($data->fullwidth) && strtolower($data->fullwidth) === 'true') 
						|| (isset($data->isresponsive) && strtolower($data->isresponsive) === 'true' && !isset($data->fullwidth)) 
						|| (isset($atts['alignment']) && $atts['alignment'] == 'wide'))
					{
						$ret .= 'max-width:100%;';
					}
					else
					{
						$ret .= 'max-width:' . $data->width . 'px;';
					}

					if (isset($data->isfullscreen) && strtolower($data->isfullscreen) === 'true')
					{
						$ret .= $has_wrapper ? 'height:500px;' : 'height:100%;';
					}

					$ret .= $has_wrapper ? 'margin:0 auto 180px;' : 'margin:0 auto;';

					if (isset($atts['alignment']))
					{
						switch ($atts['alignment']) {
							case 'left':
								$ret .= 'margin-left:0;margin-right:auto;';
								break;
							case 'right':
								$ret .= 'margin-left:auto;margin-right:0;';
								break;
						}
					}
				}
				
				if ( isset($data->paddingleft) )
					$ret .= 'padding-left:' . $data->paddingleft . 'px;';
				
				if ( isset($data->paddingright) )
					$ret .= 'padding-right:' . $data->paddingright . 'px;';
				
				if ( isset($data->paddingtop) )
					$ret .= 'padding-top:' . $data->paddingtop . 'px;';
				
				if ( isset($data->paddingbottom) )
					$ret .= 'padding-bottom:' . $data->paddingbottom . 'px;';
			
				$ret .= '">';
			}
			
			$has_woocommerce = false;
			if (class_exists('WooCommerce') && isset($data->addwoocommerceclass) && (strtolower($data->addwoocommerceclass) === 'true'))
			{
				$has_custom = false;
				if (isset($data->slides) && count($data->slides) > 0)
				{
					foreach ($data->slides as $index => $slide)
					{
						if ($slide->type == 7)
						{
							$has_custom = true;
							break;
						}
					}
				}
				if ($has_custom)
					$has_woocommerce = true;
			}
			
			// div data tag
			$ret .= '<div class="wonderpluginslider' . ($has_woocommerce ? ' woocommerce' : '') . '" id="wonderpluginslider-' . $id . '" data-sliderid="' . $id . '" data-width="' . $data->width . '" data-height="' . $data->height . '" data-skin="' . $data->skin . '"';
			
			if (isset($data->dataoptions) && strlen($data->dataoptions) > 0)
			{
				$ret .= ' ' . stripslashes($data->dataoptions);
			}
			
			$boolOptions = array('usejsforfullbrowserwidth', 'fullbrowserwidth', 'playmutedandinlinewhenautoplay', 'addextraattributes', 'autoplay', 'randomplay', 'loadimageondemand', 'transitiononfirstslide', 'autoplayvideo', 'isresponsive', 'fullwidth', 'isfullscreen', 'ratioresponsive', 'showtext', 'showtimer', 'showbottomshadow', 'navshowpreview', 'textautohide',
					'pauseonmouseover', 'lightboxresponsive', 'lightboxshownavigation', 'lightboxshowtitle', 'lightboxshowdescription', 'texteffectresponsive', 'donotinit', 'addinitscript', 'lightboxfullscreenmode', 'lightboxcloseonoverlay', 'lightboxvideohidecontrols', 'lightboxnogroup',
					'shownav', 'navthumbresponsive', 'navshowfeaturedarrow',
					'navshowplaypause', 'navshowarrow', 'navshowbuttons',
					'lightboxshowsocial', 'lightboxshowfacebook', 'lightboxshowtwitter', 'lightboxshowpinterest', 'lightboxsocialrotateeffect',
					'showsocial', 'showfacebook', 'showtwitter', 'showpinterest', 'socialrotateeffect', 'disableinlinecss',
					'triggerresize', 'lightboxautoslide', 'lightboxshowtimer', 'lightboxshowplaybutton', 'lightboxalwaysshownavarrows', 'lightboxshowtitleprefix');
			foreach ( $boolOptions as $key )
			{
				if (isset($data->{$key}) )
					$ret .= ' data-' . $key . '="' . ((strtolower($data->{$key}) === 'true') ? 'true': 'false') .'"';
			}
			
			$boolOptions = array('titleusealt');
			foreach ( $boolOptions as $key )
			{
				$ret .= ' data-' . $key . '="' . ((isset($data->{$key}) && strtolower($data->{$key}) === 'true') ? 'true': 'false') .'"';
			}

			$boolOptions = array('outputtext');
			foreach ( $boolOptions as $key )
			{
				$ret .= ' data-' . $key . '="' . ( $data->{$key} ? 'true': 'false') .'"';
			}
			
			$valOptions = array('titletag', 'descriptiontag', 'scalemode', 'arrowstyle', 'transition', 'loop', 'border', 'slideinterval', 
					'arrowimage', 'arrowwidth', 'arrowheight', 'arrowtop', 'arrowmargin',
					'navplaypauseimage', 'navarrowimage',
					'navstyle', 'navimage', 'navwidth', 'navheight', 'navspacing', 'navmarginx', 'navmarginy', 'navposition',
					'navthumbnavigationstyle', 'navthumbnavigationarrowimage', 'navthumbnavigationarrowimagewidth', 'navthumbnavigationarrowimageheight',
					'playvideoimage', 'playvideoimagewidth', 'playvideoimageheight', 'lightboxthumbwidth', 'lightboxthumbheight', 'lightboxthumbtopmargin', 'lightboxthumbbottommargin', 'lightboxbarheight', 'lightboxtitlebottomcss', 'lightboxdescriptionbottomcss',
					'textformat', 'textpositionstatic', 'textpositiondynamic', 'paddingleft', 'paddingright', 'paddingtop', 'paddingbottom', 'texteffectresponsivesize', 'textleftrightpercentforstatic',
					'fadeduration','crossfadeduration','fadeoutfadeinduration','slideduration', 'cssslideduration', 'elasticduration', 'sliceduration','blindsduration','blocksduration','shuffleduration',
					'tilesduration', 'kenburnsduration', 'flipduration', 'flipwithzoomduration',
					'threedduration','threedhorizontalduration', 'threedwithzoomduration', 'threedhorizontalwithzoomduration', 'threedflipduration', 'threedflipwithzoomduration', 'threedtilesduration',
					'threedfallback','threedhorizontalfallback', 'threedwithzoomfallback', 'threedhorizontalwithzoomfallback', 'threedflipfallback', 'threedflipwithzoomfallback', 'threedtilesfallback',
					'ratiomediumscreen', 'ratiomediumheight', 'ratiosmallscreen', 'ratiosmallheight', 
					'socialmode', 'socialposition', 'socialpositionsmallscreen', 'socialdirection', 'socialbuttonsize', 'socialbuttonfontsize',
					'navthumbcolumn', 'navthumbtitleheight', 'navthumbtitlewidth', 'navthumbresponsivemode', 'navthumbstyle',
					'navthumbmediumsize', 'navthumbmediumwidth', 'navthumbmediumheight', 'navthumbmediumcolumn', 'navthumbmediumtitleheight', 'navthumbmediumtitlewidth',
					'navthumbsmallsize', 'navthumbsmallwidth', 'navthumbsmallheight', 'navthumbsmallcolumn', 'navthumbsmalltitleheight', 'navthumbsmalltitlewidth',
					'lightboxsocialposition', 'lightboxsocialpositionsmallscreen', 'lightboxsocialdirection', 'lightboxsocialbuttonsize', 'lightboxsocialbuttonfontsize',
					'lightboxtitlestyle', 'lightboximagepercentage', 'lightboxdefaultvideovolume', 'lightboxoverlaybgcolor', 'lightboxoverlayopacity', 'lightboxbgcolor', 'lightboxtitleprefix', 'lightboxtitleinsidecss', 'lightboxdescriptioninsidecss',
					'lightboxtitleoutsidecss', 'lightboxdescriptionoutsidecss',
					'triggerresizedelay', 'lightboxslideinterval', 'lightboxtimerposition', 'lightboxtimerheight:', 'lightboxtimercolor', 'lightboxtimeropacity', 'lightboxbordersize', 'lightboxborderradius'
					);
			
			foreach ( $valOptions as $key )
			{
				if (isset($data->{$key}) )
					$ret .= ' data-' . $key . '="' . $data->{$key} . '"';
			}
			
			$cssOptions = array('textcss', 'textbgcss', 'titlecss', 'descriptioncss', 'buttoncss', 'titlecssresponsive', 'descriptioncssresponsive', 'buttoncssresponsive');
			foreach ( $cssOptions as $key )
			{
				if (isset($data->{$key}) )
					$ret .= ' data-' . $key . '="' . $this->eacape_html_quotes($data->{$key}) . '"';
			}
			
			$ret .= ' data-jsfolder="' . WONDERPLUGIN_SLIDER_URL . 'engine/"'; 
			if (isset($data->disableinlinecss) && strtolower($data->disableinlinecss) === 'true')
				$ret .= ' >';
			else
				$ret .= ' style="display:none;" >';
			
			$currentlang = $this->multilingual ? (!empty($data->lang) ? $data->lang : $this->currentlang) : null;

			// dynamic contents
			if ( !empty($atts['mediaids']) )
			{
				$mediaIds = array_map('trim', explode(",", $atts['mediaids']));

				if (!isset($data->slides))
				{
					$data->slides = array();
				}

				foreach($mediaIds as $id)
				{
					$mediaId = $id;
					
					if ($this->multilingual && $currentlang != $this->defaultlang)
					{
						$mediaId = apply_filters( 'wpml_object_id', $id, 'attachment', TRUE, $currentlang );
					}

					$data->slides[] = (object) array(
						'type' => 12,
						'mediaid' => $mediaId
					);
				}
			}

			if (isset($data->slides) && count($data->slides) > 0)
			{
				
				// process posts
				$items = array();
				foreach ($data->slides as $slide)
				{
					if ($slide->type == 6)
					{
						$items = array_merge($items, $this->get_post_items($slide));
					}
					else if ($slide->type == 7)
					{
						$items = array_merge($items, $this->get_custom_post_items($slide));
					}
					else if ($slide->type == 12)
					{
						$items = array_merge($items, $this->get_media_item($slide, $atts));
					}
					else
					{
						$items[] = $slide;
					}
				}
				
				$ret .= '<ul class="amazingslider-slides" ';
				if (isset($data->disableinlinecss) && strtolower($data->disableinlinecss) === 'true')
					$ret .= ' >';
				else
					$ret .= ' style="display:none;">';
				
				$index = 0;
				foreach ($items as $slide)
				{		
					foreach($slide as &$value)
					{
						if ( is_string($value) )
							$value = wp_kses_post($value);
					}
					
					if ($this->multilingual && $currentlang != $this->defaultlang)
					{
						$slide->title = $this->get_multilingual_slide_text($slide, 'title', $currentlang);
						$slide->description = $this->get_multilingual_slide_text($slide, 'description', $currentlang);
						$slide->alt = $this->get_multilingual_slide_text($slide, 'alt', $currentlang);
						$slide->button = $this->get_multilingual_slide_text($slide, 'button', $currentlang);
					}

					$boolOptions = array('lightbox', 'lightboxsize', 'altusetitle', 'weblinklightbox', 'usetexteffect');
					foreach ( $boolOptions as $key )
					{
						$slide->{$key} = (( isset($slide->{$key}) && (strtolower($slide->{$key}) === 'true') ) ? true: false);
					}
					
					$ret .= '<li>';
					
					if ($slide->lightbox)
					{
						$ret .= '<a href="';
						if ($slide->type == 0)
						{
							$ret .= $slide->image;
						}
						else if ($slide->type == 1)
						{
							$ret .= $slide->mp4;
							if ($slide->webm)
								$ret .= '" data-webm="' . $slide->webm;
						}
						else if ($slide->type == 2 || $slide->type == 3)
						{
							$ret .= $slide->video;
						}
						
						if ($slide->lightboxsize)
							$ret .= '" data-width="' . $slide->lightboxwidth . '" data-height="' . $slide->lightboxheight;
						
						if ($slide->description && strlen($slide->description) > 0)
							$ret .= '" data-description="' . $this->eacape_html_quotes($slide->description);
						
						$ret .= '" class="html5lightbox">';
					}
					else if ($slide->weblink && strlen($slide->weblink) > 0)
					{
						$ret .= '<a href="' . $slide->weblink . '"';
						
						if ($slide->weblinklightbox)
						{
							$ret .=  ' class="html5lightbox"';
								
							if ($slide->lightboxsize)
								$ret .= ' data-width="' . $slide->lightboxwidth . '" data-height="' . $slide->lightboxheight . '"';
								
							if ($slide->description && strlen($slide->description) > 0)
								$ret .= ' data-description="' . $this->eacape_html_quotes($slide->description) . '"';
						}
						else
						{
							if ($slide->linktarget && strlen($slide->linktarget) > 0)
								$ret .= ' target="' . $slide->linktarget . '"';
						}
						$ret .= '>';
					}
					
					$ret .= '<img';
					
					if (isset($data->addextraattributes) && strtolower($data->addextraattributes) === 'true' && !empty($data->imgextraprops))
						$ret .= ' ' . $data->imgextraprops;
					
					if ($index > 0 && isset($data->loadimageondemand) && strtolower($data->loadimageondemand) === 'true')
						$ret .= ' class="amazingslider-img-elem amazingsliderimg" data-src="';
					else
						$ret .= ' class="amazingslider-img-elem amazingsliderimg" src="';
					$ret .= $slide->image . '"';
					
					if (isset($slide->altusetitle) && isset($slide->alt) && !$slide->altusetitle && (strlen($slide->alt) > 0))
						$ret .= ' alt="' .  $this->eacape_html_quotes(strip_tags($slide->alt)) . '"';
					else
						$ret .= ' alt="' .  $this->eacape_html_quotes(strip_tags($slide->title)) . '"';
					
					$ret .= ' title="' .  $this->eacape_html_quotes($slide->title) . '"';
					$ret .= ' data-description="' .  $this->eacape_html_quotes($slide->description) . '"';
					
					if (isset($slide->usetexteffect) && $slide->usetexteffect)
					{
						$textformat = $slide->texteffect;
						$ret .= ' data-texteffect="' . $textformat . '"';
					}
					
					$ret .= ' />';
					
					if ($slide->lightbox || ($slide->weblink && strlen($slide->weblink) > 0))
					{
						$ret .= '</a>';
					}
					
					if (!$slide->lightbox)
					{
						if ($slide->type == 1)
						{
							$ret .= '<video preload="none" data-src="' . $slide->mp4 . '"';
							if ($slide->webm)
								$ret .= ' data-webm="' . $slide->webm . '"';
							$ret .= '></video>';
						}
						else if ($slide->type == 2 || $slide->type == 3)
						{
							$ret .= '<video preload="none" data-src="' . $slide->video . '"></video>';
						}
					}
					
					
					if (isset($slide->button) && $slide->button && strlen($slide->button) > 0)
					{
						if ($slide->buttonlink && strlen($slide->buttonlink) > 0)
						{
							$ret .= '<a href="' . $slide->buttonlink . '"';
							if ($slide->buttonlinktarget && strlen($slide->buttonlinktarget) > 0)
								$ret .= ' target="' . $slide->buttonlinktarget . '"';
							$ret .= '>';
						}
						
						$ret .= '<button class="amazingsliderbutton ' . $slide->buttoncss . '">' . $slide->button . '</button>';
						
						if ($slide->buttonlink && strlen($slide->buttonlink) > 0)
						{
							$ret .= '</a>';
						}
					}

					if ($data->outputtext)
					{
						$ret .= '<' . $data->titletag . '>' . $slide->title . '</' . $data->titletag. '>';
						$ret .= '<' . $data->descriptiontag . '>' . $slide->description . '</' . $data->descriptiontag.  '>';
					}
					
					$ret .= '</li>';
					
					$index++;
				}
				$ret .= '</ul>';
								
				if ( (isset($data->navstyle) && $data->navstyle == 'thumbnails') || (isset($data->navshowpreview) && strtolower($data->navshowpreview) === 'true') )
				{
					$ret .= '<ul class="amazingslider-thumbnails" ';
					if (isset($data->disableinlinecss) && strtolower($data->disableinlinecss) === 'true')
						$ret .= ' >';
					else
						$ret .= ' style="display:none;">';
					
					foreach ($items as $slide)
					{
						$thumbnailurl = (isset($data->usethumbnailurl) && strtolower($data->usethumbnailurl) === 'false' && !empty($slide->image)) ? $slide->image : $slide->thumbnail;					
						$ret .= '<li><img class="amazingslider-thumbnail-elem amazingsliderthumbnailimg" src="' . $thumbnailurl . '"';
						if (isset($slide->altusetitle) && isset($slide->alt) && !$slide->altusetitle && (strlen($slide->alt) > 0))
							$ret .= ' alt="' .  $this->eacape_html_quotes(strip_tags($slide->alt)) . '"';
						else
							$ret .= ' alt="' .  $this->eacape_html_quotes(strip_tags($slide->title)) . '"';
						$ret .= ' title="' .  $this->eacape_html_quotes($slide->title) . '"';
						$ret .= ' data-description="' .  $this->eacape_html_quotes($slide->description) . '"';
						$ret .= ' /></li>';
					}
					$ret .= '</ul>';
				}
			}


			$ret .= '</div>';
			
			$ret .= '</div>';
			
			if (isset($data->lightboxadvancedoptions) && strlen($data->lightboxadvancedoptions) > 0)
			{
				$ret .= '<div id="wpsliderlightbox_advanced_options_' . $id . '" ' . stripslashes($data->lightboxadvancedoptions) . ' ></div>';
			}
			
			if (isset($data->customtexteffect) && strlen($data->customtexteffect) > 0)
			{
				$ret .= '<div id="amazingslider_customtexteffect_' . $id . '" style="display:none;" data-texteffect="' . esc_html($data->customtexteffect) . '"></div>';
			}

			if (!empty($cssjs))
			{
				$ret .= '<script>function wonderslider_' . $id . '_appendcss(csscode) {var head=document.head || document.getElementsByTagName("head")[0];var style=document.createElement("style");head.appendChild(style);style.type="text/css";if (style.styleSheet){style.styleSheet.cssText=csscode;} else {style.appendChild(document.createTextNode(csscode));}};' . $cssjs . '</script>';
			}

			if (isset($data->addinitscript) && strtolower($data->addinitscript) === 'true')
			{
				$ret .= '<script>jQuery(document).ready(function(){jQuery(".wonderplugin-engine").css({display:"none"});jQuery(".wonderpluginslider").wonderpluginslider({forceinit:true});});</script>';
			}
			
			if (isset($data->triggerresize) && strtolower($data->triggerresize) === 'true')
			{
				$ret .= '<script>jQuery(document).ready(function(){';
				if ($data->triggerresizedelay > 0)
					$ret .= 'setTimeout(function(){jQuery(window).trigger("resize");},' . $data->triggerresizedelay . ');';
				else
					$ret .= 'jQuery(window).trigger("resize");';
				$ret .= '});</script>';
			}
			
			remove_filter('wp_kses_allowed_html', 'wonderplugin_slider_tags_allow', 'post');
			remove_filter('safe_style_css', 'wonderplugin_slider_css_allow');
			
			
			if (isset($data->customjs) && strlen($data->customjs) > 0)
			{
				$customjs = str_replace("\r", " ", $data->customjs);
				$customjs = str_replace("\n", " ", $customjs);
				$customjs = str_replace('&lt;',  '<', $customjs);
				$customjs = str_replace('&gt;',  '>', $customjs);
				$customjs = str_replace("SLIDERID", $id, $customjs);
				$ret .= '<script>' . $customjs . '</script>';
			}
		}
		else
		{
			$ret = '<p>The specified slider id does not exist.</p>';
		}
		return $ret;
	}
	
	function get_media_item($slide, $atts) {

		$items = array();

		$mediaData = get_post($slide->mediaid);
		if ( empty($mediaData) )
		{
			return $items;
		}

		$mediaType = 0;
		if ( strtolower(substr($mediaData->post_mime_type, 0, 6)) == "video/" )
		{
			$mediaType = 1;
		}

		$mediumAlt = get_post_meta($slide->mediaid, '_wp_attachment_image_alt', true);
		$altusetitle = empty($mediumAlt) ? 'true' : 'false';

		$settings = $this->get_settings();
		$imagesize = $settings['thumbnailsize'];
		
		if ($mediaType == 1)
		{
			$poster = '';
			$thumbnail = '';

			$featuredImageId = get_post_thumbnail_id($slide->mediaid);
			if ( !empty($featuredImageId) )
			{
				$postImages = wp_get_attachment_image_src($featuredImageId, 'full');
				$poster = empty($postImages) ? '' : $postImages[0]; 

				$thumbimages = wp_get_attachment_image_src($featuredImageId, $imagesize);
				$thumbnail = empty($thumbimages) ? '' : $thumbimages[0]; 
			}

			$new = array(
				'type'			=> 1,
				'mp4'			=> wp_get_attachment_url($slide->mediaid),
				'webm'			=> "",
				'image'			=> $poster,
				'thumbnail'		=> $thumbnail
			);
		}
		else
		{
			$thumbimages = wp_get_attachment_image_src($slide->mediaid, $imagesize);
			$thumbnail = empty($thumbimages) ? '' : $thumbimages[0]; 

			$new = array(
				'type'			=> 0,
				'image'			=> wp_get_attachment_url($slide->mediaid),
				'thumbnail'		=> $thumbnail
			);
		}

		$new = array_merge($new, 
			array(
				'title'			=> $mediaData->post_title,
				'description'	=> $mediaData->post_content,
				'altusetitle'	=> $altusetitle,
				'alt'			=> $mediumAlt,
				'lightbox'			=> ((isset($atts['lightbox']) && ( $atts['lightbox'] == 'true' || $atts['lightbox'] == '1' || $atts['lightbox'] == 1 )) ? 'true' : 'false'),
				'lightboxsize'		=> ((isset($atts['lightboxsize']) && ( $atts['lightboxsize'] == 'true' || $atts['lightboxsize'] == '1' || $atts['lightboxsize'] == 1 )) ? 'true' : 'false'),
				'lightboxwidth'		=> isset($atts['lightboxwidth']) ? $atts['lightboxwidth'] : 960,
				'lightboxheight'	=> isset($atts['lightboxheight']) ? $atts['lightboxheight'] : 540,
				'weblink'		=> '',
				'linktarget'	=> ''
			)
		);

		$items[] = (object) $new;

		return $items;
	}

	function get_post_items($options) {
	
		$posts = array();
	
		$args = array(
				'numberposts' 	=> $options->postnumber,
				'post_status' 	=> 'publish'
		);
		
		if (isset($options->selectpostbytags) && !empty($options->posttags))
		{
			$args['tag'] = $options->posttags;
		}
		
		if (isset($options->postdaterange) && isset($options->postdaterangeafter) && (strtolower($options->postdaterange) === 'true'))
		{
			$args['date_query'] = array(
					'after' => date('Y-m-d', strtotime('-' . $options->postdaterangeafter . ' days'))
			);
		}
		
		if ($options->postcategory == -1)
		{
			$posts = wp_get_recent_posts($args);
		}
		else
		{
			if ($options->postcategory != -2)
			{
				$args['category'] = $options->postcategory;
			}
				
			if (!empty($options->postorderby))
			{
				$args['orderby'] = $options->postorderby;
			}
				
			$posts = get_posts($args);
		}
	
		$items = array();
	
		foreach($posts as $post)
		{
			if (is_object($post))
				$post = get_object_vars($post);
				
			$thumbnail = '';
			$image = '';
			if ( has_post_thumbnail($post['ID']) )
			{
				$featured_thumb = wp_get_attachment_image_src(get_post_thumbnail_id($post['ID']), $options->featuredimagesize);
				$thumbnail = $featured_thumb[0];
				
				$featured_image = wp_get_attachment_image_src(get_post_thumbnail_id($post['ID']), 'full');
				$image = $featured_image[0];
			}
	
			$excerpt = $post['post_excerpt'];
			if (empty($excerpt))
			{
				$excerpts = explode( '<!--more-->', $post['post_content'] );
				$excerpt = $excerpts[0];
				$excerpt = strip_tags( str_replace(']]>', ']]&gt;', strip_shortcodes($excerpt)) );
			}
			$excerpt = wonderplugin_slider_wp_trim_words($excerpt, $options->excerptlength);
	
			$post_categories = wp_get_post_categories( $post['ID'] );
			$cats = array();
			foreach($post_categories as $cat_id){
					
				$cat = get_category( $cat_id );
				$cats[] = array(
						'id'   => $cat_id,
						'name' => $cat->name,
						'slug' => $cat->slug,
						'link' => get_category_link( $cat_id )
				);
			}
								
			$post_tags = wp_get_post_tags( $post['ID'] );
			$tags = array();
			foreach($post_tags as $tag)
			{
				$tags[] = array(
						'id' 	=> $tag->term_id,
						'name'	=> $tag->name,
						'slug'	=> $tag->slug,
						'link'	=> get_tag_link( $tag->term_id )
				);
			}

			$post_link = get_permalink($post['ID']);
			
			if (!isset($options->posttitlefield))
				$options->posttitlefield = '%post_title%';
				
			if (!isset($options->postdescriptionfield))
				$options->postdescriptionfield = '%post_excerpt%';
			
			
			$postdata = array_merge($post, array(
				'post_excerpt' => $excerpt,
				'post_link' => $post_link
			));
						
			foreach($cats as $catindex => $cat)
			{
				if ($catindex == 0)
				{
					$postdata['%categoryid%'] = $cat['id'];
					$postdata['%categoryname%'] = $cat['name'];
					$postdata['%categoryslug%'] = $cat['slug'];
					$postdata['%categorylink%'] = $cat['link'];
				}
				
				$postdata['%categoryid' . $catindex . '%'] = $cat['id'];
				$postdata['%categoryname' . $catindex . '%'] = $cat['name'];
				$postdata['%categoryslug' . $catindex . '%'] = $cat['slug'];
				$postdata['%categorylink' . $catindex . '%'] = $cat['link'];
			}
			
			foreach($tags as $tagindex => $tag)
			{
				if ($tagindex == 0)
				{
					$postdata['%tagid%'] = $tag['id'];
					$postdata['%tagname%'] = $tag['name'];
					$postdata['%tagslug%'] = $tag['slug'];
					$postdata['%taglink%'] = $tag['link'];
				}
				
				$postdata['%tagid' . $tagindex . '%'] = $tag['id'];
				$postdata['%tagname' . $tagindex . '%'] = $tag['name'];
				$postdata['%tagslug' . $tagindex . '%'] = $tag['slug'];
				$postdata['%taglink' . $tagindex . '%'] = $tag['link'];
			}
			
			$postmeta = get_post_meta($postdata['ID']);

			$title = $this->replace_custom_field($postdata, $postmeta, $options->posttitlefield, $options->excerptlength);
			$description = $this->replace_custom_field($postdata, $postmeta, $options->postdescriptionfield, $options->excerptlength);

			$post_item = array(
					'type'			=> 0,
					'image'			=> $image,
					'thumbnail'		=> $thumbnail,
					'title'			=> $title,
					'description'	=> $description,
					'weblink'		=> $post_link,
					'linktarget'	=> $options->postlinktarget,
					'button'		=> $options->button,
					'buttoncss'		=> $options->buttoncss,
					'buttonlink'	=> get_permalink($post['ID']),
					'buttonlinktarget'	=> $options->postlinktarget
			);
			
			if (isset($options->postlightbox))
			{
				$post_item['lightbox'] = $options->postlightbox;
				$post_item['lightboxsize'] = $options->postlightboxsize;
				$post_item['lightboxwidth'] = $options->postlightboxwidth;
				$post_item['lightboxheight'] = $options->postlightboxheight;
				
				if (isset($options->posttitlelink) && strtolower($options->posttitlelink) === 'true')
				{
					$post_item['title'] = '<a class="amazingslider-posttitle-link" href="' . $post_item['weblink'] . '"';
					if (isset($post_item['linktarget']) && strlen($post_item['linktarget']) > 0)
						$post_item['title'] .= ' target="' . $post_item['linktarget'] . '"';
					$post_item['title'] .= '>' . $title . '</a>';
				}
			}
			
			$items[] = (object) $post_item;
		}
	
		return $items;
	}
	
	function replace_custom_field($postdata, $postmeta, $field, $textlength) {
	
		$postdata = array_merge($postdata, $postmeta);
	
		$postdata = apply_filters( 'wonderplugin_slider_custom_post_field_content', $postdata );
	
		$result = $field;
	
		preg_match_all('/\\%(.*?)\\%/s', $field, $matches);
	
		if (!empty($matches) && count($matches) > 1)
		{
			foreach($matches[1] as $match)
			{
				$replace = '';
				if (array_key_exists($match, $postdata))
				{
					if (is_array($postdata[$match]))
					{
						$replace = implode(' ', $postdata[$match]);
					}
					else
					{
						$replace = $postdata[$match];
					}
						
					if ($match == 'post_content' || $match == 'post_excerpt')
						$replace = wonderplugin_slider_wp_trim_words($replace, $textlength);
				}
				$result = str_replace('%' . $match . '%', $replace, $result);
			}
		}
	
		return $result;
	}
	
	function get_custom_post_items($options) {
			
		global $post;

		$items = array();

		$args = array(
				'post_type' 		=> $options->customposttype,
				'posts_per_page'	=> $options->postnumber,
				'post_status' 	=> 'publish'
		);

		if (isset($options->postdaterange) && (strtolower($options->postdaterange) === 'true') && isset($options->postdaterangeafter) )
		{
			$args['date_query'] = array(
					'after' => date('Y-m-d', strtotime('-' . $options->postdaterangeafter . ' days'))
			);
		}

		$taxonomytotal = 0;

		$tax_query = array();

		for ($i = 0; ; $i++)
		{
			if (isset($options->{'taxonomy' . $i}) && isset($options->{'term' . $i}) && ($options->{'taxonomy' . $i} != '-1') && ($options->{'term' . $i} != '-1') )
			{
				$taxonomytotal++;
				$tax_query[] = array(
						'taxonomy' => $options->{'taxonomy' . $i},
						'field'    => 'slug',
						'terms'    => $options->{'term' . $i}
				);
			}
			else
			{
				break;
			}
		}

		if ($taxonomytotal > 1)
		{
			$tax_query['relation'] = $options->taxonomyrelation;
		}

		if ($taxonomytotal > 0)
		{
			$args['tax_query'] = $tax_query;
		}

		// meta _featured only works for WooCommerce 1 and 2
		if ( class_exists('WooCommerce') )
		{
			global $woocommerce;
			if ( version_compare( $woocommerce->version, '3.0', ">=") )
				$options->metafeatured = 'false';	
		}
		
		// woocommerce meta query
		if ( class_exists('WooCommerce') && ((isset($options->metatotalsales) && (strtolower($options->metatotalsales) === 'true')) || (isset($options->metafeatured) && (strtolower($options->metafeatured) === 'true'))) )
		{
			$meta_query = array();
				
			if (isset($options->metatotalsales) && (strtolower($options->metatotalsales) === 'true'))
			{
				$meta_query[] = array(
						'key'       => 'total_sales',
						'value'     => '0',
						'compare'   => '>='
				);

				$args['orderby'] = 'total_sales';
			}

			if (isset($options->metafeatured) && (strtolower($options->metafeatured) === 'true'))
			{
				$meta_query[] = array(
						'key'       => '_featured',
						'value'     => 'yes',
						'compare'   => '='
				);
			}

			if ( (isset($options->metatotalsales) && (strtolower($options->metatotalsales) === 'true')) && (isset($options->metafeatured) && (strtolower($options->metafeatured) === 'true')) )
			{
				$meta_query['relation'] = $options->metarelation;
			}
				
			$args['meta_query'] = $meta_query;
		}

		if ( class_exists('WooCommerce') && isset($options->metaonsale) && (strtolower($options->metaonsale) === 'true') )
		{
			$args['post__in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
		}

		$query = new WP_Query($args);
		if ($query->have_posts())
		{
			while ( $query->have_posts() )
			{
				$query->the_post();

				if ($post)
				{
					$postdata = get_object_vars($post);

					$featured_image = '';
					if (has_post_thumbnail($postdata['ID']))
					{
						$featured_image_size = (!empty($options->customfeaturedimagesize)) ? $options->customfeaturedimagesize : 'full';
						$attachment_image = wp_get_attachment_image_src(get_post_thumbnail_id($postdata['ID']), $featured_image_size);
						$featured_image = $attachment_image[0];
					}
					$postdata['featured_image'] = $featured_image;

					$postmeta = get_post_meta($postdata['ID']);

					if (class_exists('WooCommerce') && isset($postdata['ID']))
					{
						global $woocommerce;

						$is_woocommerce3 = version_compare( $woocommerce->version, '3.0', ">=");

						$product = wc_get_product($postdata['ID']);
						if ($product)
						{
							$postmeta['wc_price_html'] = $product->get_price_html();
							$postmeta['wc_price'] = wc_price( $product->get_price() );
							$postmeta['wc_regular_price'] = wc_price( $product->get_regular_price() );
							$postmeta['wc_sale_price'] = wc_price( $product->get_sale_price() );
							$postmeta['wc_rating_html'] = $is_woocommerce3 ? wc_get_rating_html( $product->get_average_rating(), $product->get_rating_count() ) : $product->get_rating_html();
							$postmeta['wc_review_count'] = $product->get_review_count();
							$postmeta['wc_rating_count'] = $product->get_rating_count();
							$postmeta['wc_average_rating'] = $product->get_average_rating();
							$postmeta['wc_total_sales'] = (int) get_post_meta( $postdata['ID'], 'total_sales', true );
						}
					}

					$postlink = get_permalink($postdata['ID']);
					$postdata['post_link'] = $postlink;

					$title = $this->replace_custom_field($postdata, $postmeta, $options->titlefield, $options->textlength);
					$description = $this->replace_custom_field($postdata, $postmeta, $options->descriptionfield, $options->textlength);
					$image = $this->replace_custom_field($postdata, $postmeta, $options->imagefield, $options->textlength);
					
					if (strtolower($options->titlelink) === 'true')
					{
						$title = '<a href="' . $postlink . '" target="'. $options->postlinktarget . '">' . $title . '</a>';
					}
					
					$post_item = array(
							'type'					=> 0,
							'image'					=> $image,
							'thumbnail'				=> $image,
							'title'					=> $title,
							'description'			=> $description,
							'linktarget'			=> $options->postlinktarget
						);
										
					if (strtolower($options->imageaction) === 'true')
					{
						if (strtolower($options->imageactionlightbox) === 'true')
						{
							$post_item['weblink'] = $image;
							$post_item['lightbox'] = (strtolower($options->imageactionlightbox) === 'true') ? 'true' : 'false';
							$post_item['weblinklightbox'] = 'false';
						}
						else if ($postlink && strlen($postlink) > 0)
						{
							$post_item['weblink'] = $postlink;
							$post_item['lightbox'] = 'false';
							$post_item['weblinklightbox'] = (strtolower($options->openpostinlightbox) === 'true') ? 'true' : 'false';
						}
						
						$post_item['lightboxsize'] = (strtolower($options->postlightboxsize) === 'true') ? 'true' : 'false';
						$post_item['lightboxwidth'] = $options->postlightboxwidth;
						$post_item['lightboxheight'] = $options->postlightboxheight;
					}

					$items[] = (object) $post_item;
				}

			}
			wp_reset_postdata();
		}

		if (isset($options->postorder) && ($options->postorder == 'ASC'))
			$items = array_reverse($items);
		
		return $items;
	}
				
	function delete_item($id) {
		
		global $wpdb;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
		
		$ret = $wpdb->query( $wpdb->prepare(
				"
				DELETE FROM $table_name WHERE id=%s
				",
				$id
		) );
		
		return $ret;
	}
	
	function trash_item($id) {
	
		return $this->set_item_status($id, 0);
	}
	
	function restore_item($id) {
	
		return $this->set_item_status($id, 1);
	}
	
	function set_item_status($id, $status) {
	
		global $wpdb;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
	
		$ret = false;
		$item_row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
		if ($item_row != null)
		{
			$data = json_decode($item_row->data, true);
			$data['publish_status'] = $status;
			$data = json_encode($data);
	
			$update_ret = $wpdb->query( $wpdb->prepare( "UPDATE $table_name SET data=%s WHERE id=%d", $data, $id ) );
			if ( $update_ret )
				$ret = true;
		}
	
		return $ret;
	}
	
	function clone_item($id) {
	
		global $wpdb, $user_ID;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
		
		$cloned_id = -1;
		
		$item_row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
		if ($item_row != null)
		{
			$time = current_time('mysql');
			$authorid = $user_ID;
			
			$ret = $wpdb->query( $wpdb->prepare(
					"
					INSERT INTO $table_name (name, data, time, authorid)
					VALUES (%s, %s, %s, %s)
					",
					$item_row->name . " Copy",
					$item_row->data,
					$time,
					$authorid
			) );
				
			if ($ret)
				$cloned_id = $wpdb->insert_id;
		}
	
		return $cloned_id;
	}
	
	function is_db_table_exists() {
	
		global $wpdb;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
	
		return ( strtolower($wpdb->get_var("SHOW TABLES LIKE '$table_name'")) == strtolower($table_name) );
	}
	
	function is_id_exist($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
	
		$slider_row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
		return ($slider_row != null);
	}
	
	function create_db_table() {
	
		global $wpdb;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
		
		$charset = '';
		if ( !empty($wpdb -> charset) )
			$charset = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( !empty($wpdb -> collate) )
			$charset .= " COLLATE $wpdb->collate";
	
		$sql = "CREATE TABLE $table_name (
		id INT(11) NOT NULL AUTO_INCREMENT,
		name tinytext DEFAULT '' NOT NULL,
		data MEDIUMTEXT DEFAULT '' NOT NULL,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		authorid tinytext NOT NULL,
		PRIMARY KEY  (id)
		) $charset;";
			
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	function save_item($item) {
		
		global $wpdb, $user_ID;
		
		if ( !$this->is_db_table_exists() )
		{
			$this->create_db_table();
		
			$create_error = "CREATE DB TABLE - ". $wpdb->last_error;
			if ( !$this->is_db_table_exists() )
			{
				return array(
						"success" => false,
						"id" => -1,
						"message" => $create_error
				);
			}
		}
		
		$table_name = $wpdb->prefix . "wonderplugin_slider";
		
		$id = $item["id"];
		$name = $item["name"];
		
		unset($item["id"]);
		$data = json_encode($item);
		
		if ( empty($data) )
		{
			$json_error = "json_encode error";
			if ( function_exists('json_last_error_msg') )
				$json_error .= ' - ' . json_last_error_msg();
			else if ( function_exists('json_last_error') )
				$json_error .= 'code - ' . json_last_error();
		
			return array(
					"success" => false,
					"id" => -1,
					"message" => $json_error
			);
		}
		
		$time = current_time('mysql');
		$authorid = $user_ID;
		
		if ( ($id > 0) && $this->is_id_exist($id) )
		{
			$ret = $wpdb->query( $wpdb->prepare(
					"
					UPDATE $table_name
					SET name=%s, data=%s, time=%s, authorid=%s
					WHERE id=%d
					",
					$name,
					$data,
					$time,
					$authorid,
					$id
			) );
			
			if (!$ret)
			{
				return array(
						"success" => false,
						"id" => $id, 
						"message" => "UPDATE - ". $wpdb->last_error
					);
			}
		}
		else
		{
			$ret = $wpdb->query( $wpdb->prepare(
					"
					INSERT INTO $table_name (name, data, time, authorid)
					VALUES (%s, %s, %s, %s)
					",
					$name,
					$data,
					$time,
					$authorid
			) );
			
			if (!$ret)
			{
				return array(
						"success" => false,
						"id" => -1,
						"message" => "INSERT - " . $wpdb->last_error
				);
			}
			
			$id = $wpdb->insert_id;
		}
		
		return array(
				"success" => true,
				"id" => intval($id),
				"message" => "Slider published!"
		);
	}
	
	function get_list_data($published_only = false) {
		
		if ( !$this->is_db_table_exists() )
			$this->create_db_table();
		
		global $wpdb;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
		
		$rows = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A);
		
		$ret = array();
		
		if ( $rows )
		{
			foreach ( $rows as $row )
			{
				if ($published_only)
				{
					$data = json_decode($row['data'], true);	
					
					if ( isset($data['publish_status']) && ($data['publish_status'] === 0) )
						continue;
				}
				
				$ret[] = array(
							"id" => $row['id'],
							'name' => $row['name'],
							'data' => $row['data'],
							'time' => $row['time'],
							'authorid' => $row['authorid']
						);
			}
		}
	
		return $ret;
	}
	
	function get_list_item_data($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
				
		return $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) , ARRAY_A);
	}
	
	function get_item_data($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "wonderplugin_slider";
	
		$ret = "";
		$item_row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id) );
		if ($item_row != null)
		{
			$ret = $item_row->data;
		}

		return $ret;
	}
	
	
	function get_settings() {
	
		$userrole = get_option( 'wonderplugin_slider_userrole' );
		if ( $userrole == false )
		{
			update_option( 'wonderplugin_slider_userrole', 'manage_options' );
			$userrole = 'manage_options';
		}
		
		$keepdata = get_option( 'wonderplugin_slider_keepdata', 1 );
		
		$disableupdate = get_option( 'wonderplugin_slider_disableupdate', 0 );
		
		$supportwidget = get_option( 'wonderplugin_slider_supportwidget', 1 );
		
		$addjstofooter = get_option( 'wonderplugin_slider_addjstofooter', 0 );
		
		$jsonstripcslash = get_option( 'wonderplugin_slider_jsonstripcslash', 1 );
		
		$usepostsave = get_option( 'wonderplugin_slider_usepostsave', 0 );
		
		$addextrabackslash = get_option( 'wonderplugin_slider_addextrabackslash', 0 );
		
		$thumbnailsize = get_option( 'wonderplugin_slider_thumbnailsize' );
		if ( $thumbnailsize == false )
		{
			update_option( 'wonderplugin_slider_thumbnailsize', 'large' );
			$thumbnailsize = 'large';
		}
		
		$jetpackdisablelazyload = get_option( 'wonderplugin_slider_jetpackdisablelazyload', 1 );

		$supportmultilingual = get_option( 'wonderplugin_slider_supportmultilingual', 1 );

		$settings = array(
				"userrole" => $userrole,
				"thumbnailsize" => $thumbnailsize,
				"keepdata" => $keepdata,
				"disableupdate" => $disableupdate,
				"supportwidget" => $supportwidget,
				"addjstofooter" => $addjstofooter,
				"jsonstripcslash" => $jsonstripcslash,
				"usepostsave" => $usepostsave,
				"addextrabackslash" => $addextrabackslash,
				"jetpackdisablelazyload" => $jetpackdisablelazyload,
				"supportmultilingual" => $supportmultilingual
		);
		
		return $settings;	
	}
	
	function save_settings($options) {
	
		if (!isset($options) || !isset($options['userrole']))
			$userrole = 'manage_options';
		else if ( $options['userrole'] == "Editor")
			$userrole = 'moderate_comments';
		else if ( $options['userrole'] == "Author")
			$userrole = 'upload_files';
		else
			$userrole = 'manage_options';
		update_option( 'wonderplugin_slider_userrole', $userrole );
		
		if (!isset($options) || !isset($options['keepdata']))
			$keepdata = 0;
		else
			$keepdata = 1;
		update_option( 'wonderplugin_slider_keepdata', $keepdata );
		
		if (!isset($options) || !isset($options['disableupdate']))
			$disableupdate = 0;
		else
			$disableupdate = 1;
		update_option( 'wonderplugin_slider_disableupdate', $disableupdate );
		
		if (!isset($options) || !isset($options['supportwidget']))
			$supportwidget = 0;
		else
			$supportwidget = 1;
		update_option( 'wonderplugin_slider_supportwidget', $supportwidget );
		
		if (!isset($options) || !isset($options['addjstofooter']))
			$addjstofooter = 0;
		else
			$addjstofooter = 1;
		update_option( 'wonderplugin_slider_addjstofooter', $addjstofooter );
		
		if (!isset($options) || !isset($options['jsonstripcslash']))
			$jsonstripcslash = 0;
		else
			$jsonstripcslash = 1;
		update_option( 'wonderplugin_slider_jsonstripcslash', $jsonstripcslash );
		
		if (!isset($options) || !isset($options['usepostsave']))
			$usepostsave = 0;
		else
			$usepostsave = 1;
		update_option( 'wonderplugin_slider_usepostsave', $usepostsave );
		
		if (!isset($options) || !isset($options['addextrabackslash']))
			$addextrabackslash = 0;
		else
			$addextrabackslash = 1;
		update_option( 'wonderplugin_slider_addextrabackslash', $addextrabackslash );
		
		if (isset($options) && isset($options['thumbnailsize']))
			$thumbnailsize = $options['thumbnailsize'];
		else
			$thumbnailsize = 'large';
		update_option( 'wonderplugin_slider_thumbnailsize', $thumbnailsize );

		if (!isset($options) || !isset($options['jetpackdisablelazyload']))
			$jetpackdisablelazyload = 0;
		else
			$jetpackdisablelazyload = 1;
		update_option( 'wonderplugin_slider_jetpackdisablelazyload', $jetpackdisablelazyload );

		if (!isset($options) || !isset($options['supportmultilingual']))
			$supportmultilingual = 0;
		else
			$supportmultilingual = 1;
		update_option( 'wonderplugin_slider_supportmultilingual', $supportmultilingual );
	}
	
	function get_plugin_info() {
	
		$info = get_option('wonderplugin_slider_information');
		if ($info === false)
			return false;
	
		return unserialize($info);
	}
	
	function save_plugin_info($info) {
	
		update_option( 'wonderplugin_slider_information', serialize($info) );
	}
	
	function check_license($options) {
	
		$ret = array(
				"status" => "empty"
		);
	
		if ( !isset($options) || empty($options['wonderplugin-slider-key']) )
		{
			return $ret;
		}
	
		$key = sanitize_text_field( $options['wonderplugin-slider-key'] );
		if ( empty($key) )
			return $ret;
	
		$update_data = $this->controller->get_update_data('register', $key);
		if( $update_data === false )
		{
			$ret['status'] = 'timeout';
			return $ret;
		}
	
		if ( isset($update_data->key_status) )
			$ret['status'] = $update_data->key_status;
	
		return $ret;
	}
	
	function deregister_license($options) {
	
		$ret = array(
				"status" => "empty"
		);
	
		if ( !isset($options) || empty($options['wonderplugin-slider-key']) )
			return $ret;
	
		$key = sanitize_text_field( $options['wonderplugin-slider-key'] );
		if ( empty($key) )
			return $ret;
	
		$info = $this->get_plugin_info();
		$info->key = '';
		$info->key_status = 'empty';
		$info->key_expire = 0;
		$this->save_plugin_info($info);
	
		$update_data = $this->controller->get_update_data('deregister', $key);
		if ($update_data === false)
		{
			$ret['status'] = 'timeout';
			return $ret;
		}
	
		$ret['status'] = 'success';
	
		return $ret;
	}
}