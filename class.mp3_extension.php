<?php

include_once __DIR__.'/getid3/getid3/getid3.php';

class Mp3ExtController {
	public $pluginName = 'mp3_extension';
	public $pre = 'mp3ext_';
	protected $groupId = 0;
	protected $remote = false; // getFromGithuv cache
	protected $version = false;
	protected $githubInfoUrl = 'https://raw.githubusercontent.com/utopszkij/mp3_extension/master/newver-info.json';
		
	
	function __construct() {
		// ACF group ellenörzése, ha nincs létrehozása
		$this->groupId = $this->acfGroupCheck();
		// stendert ACF fieldek ellenörzése, ha nincs létrehozása
		$this->acfFieldCheck($this->groupId, 'mp3ext_band','band');
		$this->acfFieldCheck($this->groupId, 'mp3ext_composer','composer');
		$this->acfFieldCheck($this->groupId, 'mp3ext_genre','genre');
		$this->acfFieldCheck($this->groupId, 'mp3ext_length','length');
		$this->acfFieldCheck($this->groupId, 'mp3ext_publisher','publisher');
		$this->acfFieldCheck($this->groupId, 'mp3ext_year','year');
		$this->acfFieldCheck($this->groupId, 'mp3ext_comment','comment');
		$this->acfFieldCheck($this->groupId, 'mp3ext_url_user','url_user');
	}
	
	/**
	* verzió infok olvasása a github -ról (lásd github.com/utopszkij/mp3_extension/newver-info.json)
	* többször hivja a wp az admin képernyő megjelenítés közben, idő nyerés céljából ez a rutin csak egyszer olvas
	* a githubról, ismételt hivásánál a memóriba tárolt adatot adja vissza.
	* @return object
	*/
	public function getFromGithub() {
		if ($this->remote) {
			return $this->remote;		
		}
		$remote = wp_remote_get( $this->githubInfoUrl, array(
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'application/json'
			) )
		);
		if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
			set_transient($this->pre.$this->pluginName, $remote, 43200 ); // 12 hours cache
		}
		$this->remote = $remote;
		return $remote;
	}	
	    
    /**
     * ellenörzi meg van-e a szükséges ACF group?, ha nincs létrehozza
     * @return int - az ACF group id-je
     */
    protected function acfGroupCheck():int {
        global $wpdb;
        $res = $wpdb->get_results('select * from '.$wpdb->prefix.'posts
            where post_status = "publish" and post_type = "acf-field-group" and
            post_content like "%audio%"');
        if (!$res) {
            $acfGroup = new stdClass();
            $content = 'a:7:{s:8:"location";a:2:{i:0;a:1:{i:0;a:3:{s:5:"param";s:11:"post_format";s:8:"operator";s:2:"==";s:5:"value";s:5:"audio";}}i:1;a:1:{i:0;a:3:{s:5:"param";s:10:"attachment";s:8:"operator";s:2:"==";s:5:"value";s:5:"audio";}}}s:8:"position";s:6:"normal";s:5:"style";s:7:"default";s:15:"label_placement";s:3:"top";s:21:"instruction_placement";s:5:"label";s:14:"hide_on_screen";s:0:"";s:11:"description";s:0:"";}';
            $newPost = array(
                'post_title'     => 'mp3_extension',
                'post_excerpt'   => sanitize_title('mp3_extension'),
                'post_name'      => 'group_' . uniqid(),
                'post_date'      => date( 'Y-m-d H:i:s' ),
                'comment_status' => 'closed',
                'post_status'    => 'publish',
                'post_content'   => $content,
                'post_type'      => 'acf-field-group',
            );
            $result = wp_insert_post($newPost);
        } else {
            $result = $res[0]->ID;
        }
        return $result;
    }
    
    /**
     * ellenörzi meg van-e az acfField? ha nincs létrehozza (szöveges tipusú mező)
     * @param int $groupId
     * @param string $fieldName
     * @param string $label
     */
    protected function acfFieldCheck(int $groupId, string $fieldName, string $label)  {
        global $wpdb;
        $content = 'a:10:{s:4:"type";s:4:"text";s:12:"instructions";s:0:"";s:8:"required";i:0;s:17:"conditional_logic";i:0;s:7:"wrapper";a:3:{s:5:"width";s:0:"";s:5:"class";s:0:"";s:2:"id";s:0:"";}s:13:"default_value";s:0:"";s:11:"placeholder";s:0:"";s:7:"prepend";s:0:"";s:6:"append";s:0:"";s:9:"maxlength";s:0:"";}';
        $res = $wpdb->get_results('select * from '.$wpdb->prefix.'posts
            where post_status = "publish" and post_type = "acf-field" and
            post_parent = '.$groupId.' and post_excerpt = "'.$fieldName.'"');
        if (!$res) {
            $newPost = array(
                'post_parent'    => $groupId,
                'post_title'     => $label,
                'post_excerpt'   => sanitize_title($fieldName),
                'post_name'      => 'field_' . uniqid(),
                'post_date'      => date( 'Y-m-d H:i:s' ),
                'comment_status' => 'closed',
                'post_status'    => 'publish',
                'post_content'   => $content,
                'post_type'      => 'acf-field'
            );
            wp_insert_post($newPost);
        }
        return;
    }
    
    /**
    * mp3 file teljes elérési utvonal kinyerése post -ból
	* postmeta_"_wp_attached_file" tartalmazza az elérési utvonalat wp-content/uploads/...a postmeta value értéke...
    * @param object $post
    * @return string
    */
    public function getFilePath($post): string {
    	$result = __DIR__.'/../../uploads/';
    	$res = get_field('_wp_attached_file',$post->ID);
    	if ($res) {
			$result .= $res;    	
    	}
    	return $result;
    }
    
    /**
    * admin menüpont
    */
    public function admin() {
		echo '<h2>'.$this->projectName.' plugin</h2> 
	 	<p>kibővített mp3 info kezelés a wp admin oldalon</p>
	 	<p> </p>
	 	<p><a href="https://github.com/utopszkij/mp3_extension">plugin web oldal</a></p>
	 	<p> </p>
	 	<p>extended mp3 info managment in wp admin site</p>
	 	<p> </p>
	 	<p><a href="https://github.com/utopszkij/mp3_extension">plugin web site</a></p>
	 	<p> </p>
	 	';
    }

	/**
	* picture adat feldolgozása
	* @param array
	* @return string  '<img..... />';
	*/
	protected function processPicture($fv) {
		$variable = $fv[0];	
		$value = $fv[0]['data'];
		$imageinfo = array();
		$returnstring = '';
		if ($imagechunkcheck = getid3_lib::GetDataImageSize($value, $imageinfo)) {									
			$returnstring = '<img src="data:'.$variable['image_mime'].';base64,'.base64_encode($value).'" height="200" />';
		}
	}				
														

    
    /**
    * mp3 file feldolgozása, adat tárolás az ACF mezőkbe, ha azok még üresek vagy nem léteznek
    * ha maga az ACF field definició sem létezik akkor létrehozza
    * field_get(postId, name)   és field_update(postid, name, value) funkciókkal
    */
    public function process(int  $post_id, string $filePath) {
    	global $post;
		$getId3 = new getID3();
		$res = $getId3->analyze($filePath);
		$getId3->CopyTagsToComments($res);
		$datas = [];
		if (isset($res['comments'])) {
			foreach($res['comments'] as $fn => $fv) {
				if ($fn == 'picture') {
					$datas[$fn] = $this->processPicture($fv);					
				} else {
					$datas[$fn] = implode(',',$fv);
				}			
			}		
		}		
		if (isset($res['comments_html'])) {
			foreach($res['comments_html'] as $fn => $fv) {
				$datas[$fn] = implode(',',$fv);			
			}		
		}	
		if (isset($res['id3v1'])) {
			if (isset($res['id3v1']['APIC'])) {
				$datas['picture'] = $this->processPicture($res['id3v1']['APIC']);					
			}		
		}	
		if (isset($res['id3v2'])) {
			if (isset($res['id3v2']['APIC'])) {
				$datas['picture'] = $this->processPicture($res['id3v2']['APIC']);					
			}		
		}	
		if (isset($res['tags_html'])) {
			if (isset($res['tags_html']['id3v1'])) {			
				foreach($res['tags_html']['id3v1'] as $fn => $fv) {
					$datas[$fn] = implode(',',$fv);			
				}		
			}
		}		
		if (isset($res['tags_html'])) {
			if (isset($res['tags_html']['id3v2'])) {			
				foreach($res['tags_html']['id3v2'] as $fn => $fv) {
					$datas[$fn] = implode(',',$fv);			
				}		
			}
		}
		if (isset($res['fileformat'])) {
			$datas['fileformat'] = $res['fileformat'];
		}		
		if (isset($res['filename'])) {
			$datas['filename'] = $res['filename'];
		}		
		if (isset($res['filesize'])) {
			$datas['filesize'] = $res['filesize'];
		}		
		if (isset($res['mime_type'])) {
			$datas['mime_type'] = $res['mime_type'];
		}		
		if (isset($res['playtime_string'])) {
			$datas['playtime'] = $res['playtime_string'];
		}		
		if (isset($res['mpeg'])) {
			if (isset($res['mpeg']['audio'])) {
				if (isset($res['mpeg']['audio']['copyright'])) {
					$datas['copyright'] = $res['mpeg']['audio']['copyright'];
				}
			}
		}
		
		// felesleges mezők, illetve az alap wp is kezeli őket
		if (isset($datas['music_cd_identifier'])) {
			unset($datas['music_cd_identifier']);		
		}
		if (isset($datas['filename'])) {
			unset($datas['filename']);		
		}
		if (isset($datas['filesize'])) {
			unset($datas['filesize']);		
		}
		if (isset($datas['fileformat'])) {
			unset($datas['fileformat']);		
		}
		if (isset($datas['mime_type'])) {
			unset($datas['mime_type']);		
		}
		if (isset($datas['playtime'])) {
			unset($datas['playtime']);		
		}

		// wp meta adatbn tárolt dolgok frissitése		
		$meta = get_field('_wp_attachment_metadata', $post_id);
		$metaUpdated = false;
		if (!isset($meta['album'])) {
			$meta['album'] = '';			
		}
		if (!isset($meta['artist'])) {
			$meta['artist'] = '';			
		}
		if (isset($datas['album'])) {
			if ($meta['album'] == '') {
			  $meta['album'] = $datas['album'];
			  $metaUpdated = true;
			}  
			unset($datas['album']);		
		}	
		if (isset($datas['artist'])) {
			if ($meta['artist'] == '') {
			  $meta['artist'] = $datas['artist'];
			  $metaUpdated = true;
			}  
			unset($datas['artist']);		
		}
		if ($metaUpdated) {	
			update_field('_wp_attachment_metadata', $meta, $post_id);
		}
		
		// post_title frissitése
		if (isset($datas['title'])) {
			if ($post->post_title == '') {
				$post->post_title = $datas['title'];			
			}
			unset($datas['title']);		
		}	
		
		// többi adatt tárolása ACF -be, képet nem tároljuk, csak megjelenitjük
		foreach ($datas as $fn => $fv) {
			if ($fn != 'picture') {
				$this->acfFieldCheck($this->groupId, 'mp3ext_'.$fn, $fn);
				$value = get_field('mp3ext_'.$fn, $post_id);
				if ((!$value) | ($value == '')) {
					update_field('mp3ext_'.$fn, $fv, $post_id);			
				}
			}
		}
		
		// kép megjelenítése
		if (isset($datas['picture'])) {
			echo $datas['picture'];		
		}
    }
    
}
?>