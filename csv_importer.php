<?php
/*
Plugin Name: Multisite CSV Importer
Description: -
Version: 0.1.0
Author: Crystal Barton
*/

/**
 * based on CSV Importer
 * Original Author: Denis Kobozev
 *
 * LICENSE: The MIT License {{{
 *
 * Copyright (c) <2009> <Denis Kobozev>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author    Denis Kobozev <d.v.kobozev@gmail.com>
 * @copyright 2009 Denis Kobozev
 * @license   The MIT License
 * }}}
 */



add_action( 'network_admin_menu', 'mcsv_add_network_admin_page' );


/**
 * Creates the 'Multisite CSV Importer' admin page under 'Network Admin'.
 *
 * @return void
 */
function mcsv_add_network_admin_page()
{
    require_once( ABSPATH.'/wp-admin/admin.php' );
    $plugin = new MultisiteCSVImporterPlugin();

	add_submenu_page(
		'settings.php',
		__('Multisite CSV Importer', 'multisite-csv-importer'),
		__('Multisite CSV Importer', 'multisite-csv-importer'),
		'manage_options',
		'multisite-csv-importer',
		array( $plugin, 'form' )
	);
}



class MultisiteCSVImporterPlugin
{
    var $log = array();

    /**
     * Determine value of option $name from database, $default value or $params,
     * save it to the db if needed and return it.
     *
     * @param string $name
     * @param mixed  $default
     * @param array  $params
     * @return string
     */
    function process_option($name, $default, $params)
    {
        if (array_key_exists($name, $params)) {
            $value = stripslashes($params[$name]);
        } elseif (array_key_exists('_'.$name, $params)) {
            // unchecked checkbox value
            $value = stripslashes($params['_'.$name]);
        } else {
            $value = null;
        }
        $stored_value = get_option($name);
        if ($value == null) {
            if ($stored_value === false) {
                if (is_callable($default) &&
                    method_exists($default[0], $default[1])) {
                    $value = call_user_func($default);
                } else {
                    $value = $default;
                }
                add_option($name, $value);
            } else {
                $value = $stored_value;
            }
        } else {
            if ($stored_value === false) {
                add_option($name, $value);
            } elseif ($stored_value != $value) {
                update_option($name, $value);
            }
        }
        return $value;
    }



    /**
     * Plugin's interface
     *
     * @return void
     */
    function form() {
        $opt_draft = $this->process_option('csv_importer_import_as_draft',
            'publish', $_POST);

        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            $this->post(compact('opt_draft'));
        }

        // form HTML {{{
?>

<div class="wrap">
    <h2>Import CSV</h2>
    <form class="add:the-list: validate" method="post" enctype="multipart/form-data">
        <!-- Import as draft -->
        <p>
        <input name="_csv_importer_import_as_draft" type="hidden" value="publish" />
        <label><input name="csv_importer_import_as_draft" type="checkbox" <?php if ('draft' == $opt_draft) { echo 'checked="checked"'; } ?> value="draft" /> Import posts as drafts</label>
        </p>

        <!-- File input -->
        <p><label for="csv_import">Upload file:</label><br/>
            <input name="csv_import" id="csv_import" type="file" value="" aria-required="true" /></p>
        <p class="submit"><input type="submit" class="button" name="submit" value="Import" /></p>
    </form>
</div><!-- end wrap -->

<?php
        // end form HTML }}}

    }

    function print_messages() {
        if (!empty($this->log)) {

        // messages HTML {{{
?>

<div class="wrap">
    <?php if (!empty($this->log['error'])): ?>

    <div class="error">

        <?php foreach ($this->log['error'] as $error): ?>
            <p><?php echo $error; ?></p>
        <?php endforeach; ?>

    </div>

    <?php endif; ?>

    <?php if (!empty($this->log['notice'])): ?>

    <div class="updated fade">

        <?php foreach ($this->log['notice'] as $notice): ?>
            <p><?php echo $notice; ?></p>
        <?php endforeach; ?>

    </div>

    <?php endif; ?>
</div><!-- end wrap -->

<?php
        // end messages HTML }}}

            $this->log = array();
        }
    }

    /**
     * Handle POST submission
     *
     * @param array $options
     * @return void
     */
    function post($options)
    {
        if( empty($_FILES['csv_import']['tmp_name']) )
        {
            $this->log['error'][] = 'No file uploaded, aborting.';
            $this->print_messages();
            return;
        }

        require_once 'File_CSV_DataSource/DataSource.php';

        $time_start = microtime( true );
        $csv = new File_CSV_DataSource;
        $file = $_FILES['csv_import']['tmp_name'];
        $this->stripBOM( $file );

        if( !$csv->load($file) )
        {
            $this->log['error'][] = 'Failed to load file, aborting.';
            $this->print_messages();
            return;
        }

        // pad shorter rows with empty values
        //$csv->symmetrize();

        // WordPress sets the correct timezone for date functions somewhere
        // in the bowels of wp_insert_post(). We need strtotime() to return
        // correct time before the call to wp_insert_post().
        $tz = get_option('timezone_string');
        if( $tz && function_exists('date_default_timezone_set') )
        {
            date_default_timezone_set($tz);
        }

        $skipped = 0;
        $this->imported = 0;
        $comments = 0;
        
        $this->log['notice'][] = 'Row count: '.count($csv->get_rows());
        
        foreach( $csv->get_rows() as $data )
        {
        	//$this->log['notice'][] = print_r($data, TRUE);
        	//continue;
        	
        	if( empty($data['site']) )
        	{
        		$this->log['error'][] = "The site must be specified.";
   				continue;
        	}
        	if( empty($data['type']) )
        	{
        		$this->log['error'][] = "The type must be specified.";
   				continue;
        	}
        	if( empty($data['action']) )
        	{
        		$this->log['error'][] = "The action must be specified.";
   				continue;
        	}
        	$data['type'] = trim(strtolower($data['type']));
        	$data['action'] = trim(strtolower($data['action']));

			// get site id
			if( ($site_id = $this->get_site_id($data['site'])) === null )
			{
        		$this->log['error'][] = "The site does not exist: ".$data['site'];
			}
			
        	//$this->log['notice'][] = "Site ID: ".$site_id." :: Action: ".$data['action']." :: Type: ".$data['type'];
        	
        	switch( $data['type'] )
        	{
        		case 'post':
        		case 'page':
        			if( empty($data['title']) )
        			{
        				$this->log['error'][] = "The title of the ".$data['type']." must be specified.";
        				continue;
        			}
        			if( !in_array( $data['action'], array('add','update','replace','prepend','append','delete','grep') ))
        			{
        				$this->log['error'][] = "Invalid action type '".$data['action']."' for ".$data['type'].'.';
        				continue;
        			}
        			switch_to_blog($site_id);
        			call_user_func( array(&$this, $data['action'].'_post'), $data );
        			restore_current_blog();
        			break;
        			
        		case 'link':
        			if( empty($data['name']) )
        			{
        				$this->log['error'][] = "The name of the link must be specified.";
        				continue;
        			}
        			if( !in_array( $data['action'], array('add','update','replace','delete','grep') ))
        			{
        				$this->log['error'][] = "Invalid action type '".$data['action']."' for link.";
        				continue;
        			}
        			switch_to_blog($site_id);
        			call_user_func( array(&$this, $data['action'].'_link'), $data );
        			restore_current_blog();
        			break;
        			
        		default:
        			$this->log['error'][] = "Invalid type: '".$data['type']."'";
       				continue;
        			break;
        	}
        }

        if (file_exists($file)) {
            @unlink($file);
        }

        $exec_time = microtime(true) - $time_start;

        if ($skipped) {
            $this->log['notice'][] = "<b>Skipped {$skipped} posts (most likely due to empty title, body and excerpt).</b>";
        }
        $this->log['notice'][] = sprintf("<b>Imported {$this->imported} posts and {$comments} comments in %.2f seconds.</b>", $exec_time);
        $this->print_messages();
    }
    
	
    
    /**
     * Gets the site's id based on the blog name provided.
     *
     * @param string $name
     * @return int
     */
    function get_site_id($name)
    {
    	return get_id_from_blogname($name);
    }
    
    
    
	/**
	 * Gets the Post ID for a post or page.
	 *
	 * @param string $title
	 * @param string $type
	 * @return int|null
	 */    
    function get_post_id( $title, $type = 'post' )
    {
    	global $wpdb;
    	
    	$query = $wpdb->prepare( 
    		'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = %s AND post_type = %s',
        	$title, $type
    	);
    	$wpdb->query( $query );

    	if( $wpdb->num_rows )
    	{
    		return $wpdb->get_var( $query );
    	}

		return NULL;    	
    }
    


	/**
	 * Retreives the data for a post or page.
	 *
	 * @param string $title
	 * @param string $type
	 * @return array|null
	 */
    function get_post_data( $title, $type = 'post' )
    {
    	$post_id = $this->get_post_id( $title, $type );
    	
    	if( $post_id )
    		return get_post( $post_id, 'ARRAY_A' );
    	
    	return NULL;
    }

    

	/**
	 * Prepares the post data to be used when adding or updating.
	 *
	 * @param array $data
	 * @return array
	 */
    function sanitize_post_values($data)
    {
    	$post = array();
    	
    	$post['post_type'] = $data['type'];
    	$post['action'] = $data['action'];

		// title
    	$post['post_title'] = ( !empty($data['title']) ? convert_chars($data['title']) : '' );    	
    	
    	// excerpt
    	$post['post_excerpt'] = ( !empty($data['excerpt']) ? convert_chars($data['excerpt']) : '' );
    	
    	// content
    	$post['post_content'] = ( !empty($data['content']) ? wpautop(convert_chars($data['content'])) : '' );

		// date
		if( !empty($data['date']) )
			$post['post_date'] = $this->parse_date($data['date']);
			
    	// author
    	if( !empty($data['author']) )
    	{
    		if( ($author_id = $this->get_auth_id($data['author'])) > 0 )
    			$post['post_author'] = $author_id;
    		else 
    			$this->log['notice'][] = "Author could not be found: '".$data['author']."'.";
    	}
    		
    	// slug
    	if( !empty($data['slug']) ) $post['post_name'] = $data['slug'];
    		
    	// guid
    	if( !empty($data['guid']) ) $post['guid'] = $data['guid'];

    	// parent
    	if( !empty($data['parent']) )
    	{
    		global $wpdb;
    		
    		$query = $wpdb->prepare( 
    			'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = %s AND post_type = %s',
	        	$data['parent'], $data['type']
    		);
    		$wpdb->query( $query );
    	
     		if( $wpdb->num_rows )
    			$post['post_parent'] = $wpdb->get_var( $query );
    		else 
    			$this->log['notice'][] = "Parent could not be found: '".$data['parent']."'.";
		}

    	// status
    	if( !empty($data['status']) ) 
    		$post['post_status'] = $data['status'];
    	else
    		$post['post_status'] = $this->process_option('csv_importer_import_as_draft', 'publish', $_POST);

    	// menu order
    	if( (!empty($data['menu-order'])) && (is_numeric($data['menu-order'])) )
    		$post['menu_order'] = intval($data['menu-order']);

    	// password
    	if( !empty($data['password']) ) $post['post_password'] = $data['password'];
    	
    	// taxonomy
    	$post['tax_input'] = $this->get_taxonomies($data);
    	
		return $post;
    }
    
    
    
	/**
	 * Adds a post or page, but does not check before adding so duplicates could be created.
	 * 
	 * @param array $data
	 * @return void
	 */    
    function add_post($data)
    {
    	$post = $this->sanitize_post_values($data);

    	if( $data['type'] !== 'page' )
    	{
			// categories
			$categories = $this->create_or_get_categories($data['categories'], NULL);
			$post['post_category'] = $categories['post'];
		
			// tags
			$post['tags_input'] = $data['tags'];
		}
		
        // create!
        $id = wp_insert_post($post);

        if( !id )
        {
        	if( $data['type'] !== 'page' )
        	{
            	foreach ($categories['cleanup'] as $category) 
                	wp_delete_term($category, 'category');
            }
 			$this->log['error'][] = "An error occured while adding the post: ".$post['name'];
        }
        else
        {
	        $this->imported++;
        }
    }
    
    
    
    /**
     * Updates a post or page, if it exists, with provided data.
     *
     * @param array $data
     * @param int $post_id
     * @return void
     */
    function update_post($data, $post_id = NULL)
    {
    	if( $post_id === NULL )
    	{
    	    if( ($post_id = $this->get_post_id($data['title'], $data['type'])) === NULL )
    	    {
    			$this->log['notice'][] = "Unable to retreive ".$data['type']." '".$data['title']."'.";
    			return;
    		}

		   	$post = $this->sanitize_post_values($data);

	    	if( $data['type'] !== 'page' )
	    	{
				$categories = $this->create_or_get_categories($data['categories'], NULL);
				$post['post_category'] = $categories['post'];
				$post['tags_input'] = $data['tags'];
			}
    	}
    	else
    	{
    		$post = $data;
    	}
	   	$post['ID'] = $post_id;
		
        $id = wp_update_post($post);

        if( !id )
        {
        	if( $data['type'] !== 'page' )
        	{
            	foreach ($categories['cleanup'] as $category) 
                	wp_delete_term($category, 'category');
            }
 			$this->log['error'][] = "An error occured while updating the post: ".$post['name'];
        }
        else
        {
	        $this->imported++;
        }		
    }
    
    
    
    /**
     * Replaces a post or page, if it exists, otherwise it creates the post.
     *
     * @param array $data
     * @return void
     */
    function replace_post($data)
    {
    	if( ($post_id = $this->get_post_id($data['title'], $data['type'])) === NULL )
    		$this->add_post($data);
    	else
    		$this->update_post($data, $post_id);
    }
    
    
    
    /**
     * Prepends data to a post or page's excerpt and content, if it exists.
     *
     * @param array $data
     * @return void
     */
    function prepend_post($data)
    {
    	if( ($post = $this->get_post_data($data['title'], $data['type'])) === NULL )
    		return;

		// excerpt
   		$post['post_excerpt'] = $data['excerpt'].$post['post_excerpt'];
   		
		// content
		if( ($this->find_end_tag($data['content'], array('p','div')) === NULL) &&
		    ($this->find_start_tag($post['post_content'], array('p','div')) !== NULL) )
		{
			$offset = strpos( $post['post_content'], '>' );
			if( $offset !== FALSE )
				$post['post_content'] = $this->str_insert($data['content'], $post['post_content'], $offset+1);
		}
		else
		{
	   		$post['post_content'] = $data['content'].$post['post_content'];
		}

		//$this->log['notice'][] = '<pre>'.htmlspecialchars($post['post_content']).'</pre>';
		//$this->log['notice'][] = '------------------------------------------------------';

    	$this->update_post($post, $post['ID']);
    }
    
    

    /**
     * Appends data to a post or page's excerpt and content, if it exists.
     *
     * @param array $data
     * @return void
     */
    function append_post($data)
    {
    	if( ($post = $this->get_post_data($data['title'], $data['type'])) === NULL )
    		return;

   		$post['post_excerpt'] = $post['post_excerpt'].$data['excerpt'];

		// content
		if( ($this->find_start_tag($data['content'], array('p','div')) === NULL) &&
		    ($this->find_end_tag($post['post_content'], array('p','div')) !== NULL) )
		{
			$offset = strrpos( $post['post_content'], '<' );
			if( $offset !== FALSE )
				$post['post_content'] = $this->str_insert($data['content'], $post['post_content'], $offset);
		}
		else
		{
	   		$post['post_content'] = $post['post_content'].$data['content'];
		}
		
		//$this->log['notice'][] = '<pre>'.htmlspecialchars($post['post_content']).'</pre>';
		//$this->log['notice'][] = '------------------------------------------------------';
    	
    	$this->update_post($post, $post['ID']);
    }



	/**
	 * Deletes a post or page, if it exists.
	 *
	 * @param array $data
	 * @return void
	 */    
    function delete_post($data)
    {
    	if( ($post_id = $this->get_post_id( $data['title'], $data['type'] )) === NULL )
    		return;

		if( wp_delete_post( $post_id, TRUE ) === false )
		{
 			$this->log['error'][] = "An error occured while deleting the post: ".$post['name'];
		}
		else
		{
        	$this->imported++;
        }
    }



	/**
	 * Updates a portion of a post or page using a regex expression and replacement text.
	 *
	 * @param array $data
	 * @param void
	 */    
    function grep_post($data)
    {
    	if( empty($data['subject']) )
    	{
    		$this->log['error'][] = "The subject for a grep must be specified.";
    		return;
    	}
    	if( empty($data['regex']) )
    	{
    		$this->log['error'][] = "The regex for a grep must be specified.";
    		return;
    	}
    	if( !isset($data['replace-text']) )
    	{
    		$this->log['error'][] = "The replacement text for the grep must be specified.";
    		return;
    	}
        $data['subject'] = trim(strtolower($data['subject']));

    	if( ($post = $this->get_post_data($data['title'], $data['type'])) === NULL )
    	{
			$this->log['notice'][] = "Unable to find ".$data['type'].": '".$data['title']."'.";
			return;
    	}
    	
    	$key = '';
		switch( $data['subject'] )
		{
			case 'title'  : $key = 'post_title'; break;
			case 'excerpt': $key = 'post_excerpt'; break;
			case 'content': $key = 'post_content'; break;
			case 'slug'   : $key = 'post_name'; break;
			case 'guid'   : $key = 'guid'; break;
			default:
				$this->log['error'][] = "Invalid subject type: '".$data['subject']."'.";
				break;
		}

		$pattern = '#'.$data['regex'].'#';
        if( preg_match($pattern, $post[$key]) != 1 )
        {
        	$this->log['error'][] = "No grep match '".$data['regex']."' for post '".$data['title']."'.";
        	return;
        }

		$post[$key] = preg_replace($pattern, $data['replace-text'], $post[$key]);
		$this->update_post($post, $post['ID']);
    }
    
    
    
    /**
     * Gets the link id of a link.
     *
     * @param string $name
     * @return int|null
     */
    function get_link_id( $name )
    {
    	global $wpdb;
    	
    	$query = $wpdb->prepare( 
    		'SELECT link_id FROM ' . $wpdb->links . ' WHERE link_name = %s',
        	$name
    	);
    	$wpdb->query( $query );
    	
    	if( $wpdb->num_rows )
    	{
    		return $wpdb->get_var( $query );
    	}

		return NULL;    	
    }
    


	/**
	 * Gets the data for a link.
	 *
	 * @param string $name
	 * @return array|null
	 */
	function get_link_data( $name )
    {
    	$link_id = $this->get_link_id( $name );
    	
    	if( $link_id )
    		return ((array) get_link_to_edit( $link_id ));
    	
    	return NULL;
    }
    
    
    
	/**
	 * Prepares the link data to be used when adding or updating.
	 *
	 * @param array $data
	 * @return array
	 */
	function sanitize_link_values( $data )
	{
    	$link = array();
    	
    	$link['type'] = $data['type'];
    	$link['action'] = $data['action'];

		// name
    	$link['link_name'] = ( !empty($data['name']) ? convert_chars($data['name']) : '' );    	
    	
    	// url
    	$link['link_url'] = ( !empty($data['url']) ? convert_chars($data['url']) : '' );
    	
    	// description
    	$link['link_description'] = ( !empty($data['description']) ? convert_chars($data['description']) : '' );
    	
    	// target
    	$link['link_target'] = ( in_array($data['target'], array('_blank','_top','_none')) ? $data['target'] : null );
    	
		return $link;
	}
    
    
    
    /**
     * Adds a link without checking if it already exists, which can create duplicates.
     *
     * @param array $data
     * @return void
     */
    function add_link($data)
    {
     	$link = $this->sanitize_link_values($data);

		if( !empty($data['categories']) )
		{
			$categories = $this->create_or_get_link_categories($data);
			$link['link_category'] = $categories['link'];
		}

        $id = wp_insert_link($link);
        
        if( !id )
        {
            foreach ($categories['cleanup'] as $category) 
                wp_delete_term($category, 'link_category');
			$this->load['error'][] = "An error occured while adding the link: ".$link['name'];
        }
        else
        {
	        $this->imported++;
	    }
    }

	
	
	/**
	 * Updates a link, if it exists.
	 *
	 * @param array $data
	 * @param int $link_id
	 * @return void
	 */    
    function update_link($data, $link_id = NULL)
    {
    	if( $link_id === NULL )
    	{
    	    if( ($link_id = $this->get_link_id($data['name'])) === NULL )
    	    {
    			$this->log['notice'][] = "Unable to retreive ".$data['type']." '".$data['name']."'.";
    			return;
    		}

		   	$link = $this->sanitize_link_values($data, $link_id);

			if( !empty($data['categories']) )
			{
				$categories = $this->create_or_get_link_categories($data);
				$link['link_category'] = $categories['link'];
			}
    	}
    	else
    	{
    		$link = $data;
    	}
	   	$link['link_id'] = $link_id;
		
    	$id = wp_update_link($link);
    	
    	if( $id !== intval($link_id) )
    	{
	    	$this->log['error'][] = "An error occured while updating the link: ".$data['name'];
	    }
	    else
       	{
	        $this->imported++;
		}
    }
    
    
    
    /**
     * Replaces a link, if it exists, otherwise the link is added.
     *
     * @param array $data
     * @return void
     */
    function replace_link($data)
    {
    	if( ($link_id = $this->get_link_id($data['name'])) === NULL )
    		$this->add_link($data);
		else
			$this->update_link($data, $link_id);
    }



	/**
	 * Deletes a link, if it exists.
	 *
	 * @param array $data
	 * @return void
	 */    
    function delete_link($data)
    {
    	if( ($link_id = $this->get_link_id($data['name'])) === NULL )
    		return;

		if( wp_delete_link($link_id) === false )
		{
 			$this->log['error'][] = "An error occured while deleting the link: ".$link['name'];
        }
        else
        {
        	$this->imported++;
        }
    }




	/**
	 * Updates a portion of a link using a regex expression and replacement text.
	 *
	 * @param array $data
	 * @return void
	 */    
    function grep_link($data)
    {
    	if( empty($data['subject']) )
    	{
    		$this->log['error'][] = "The subject for a grep must be specified.";
    		return;
    	}
    	if( empty($data['regex']) )
    	{
    		$this->log['error'][] = "The regex for a grep must be specified.";
    		return;
    	}
    	if( !isset($data['replace-text']) )
    	{
    		$this->log['error'][] = "The replacement text for the grep must be specified.";
    		return;
    	}
        $data['subject'] = trim(strtolower($data['subject']));

    	if( ($link = $this->get_link_data($data['name'])) === NULL )
    	{
			return;
    	}
    	
    	$key = '';
		switch( $data['subject'] )
		{
			case 'name': $key = 'link_name'; break;
			case 'url' : $key = 'link_url'; break;
			case 'description': $key = 'link_description'; break;
			default:
				$this->log['error'][] = "Invalid subject type: '".$data['subject']."'.";
				break;
		}

		$pattern = '#'.$data['regex'].'#';
        if( preg_match($pattern, $post[$key]) != 1 )
        {
        	$this->log['error'][] = "No grep match '".$data['regex']."' for link '".$data['name']."'.";
        	return;
        }

		$link[$key] = preg_replace('#'.$data['regex'].'#', $data['replace-text'], $link[$key]);
		$this->update_link($link, $link['link_id']);    	
    }
	
	
	
	/**
	 * Get the link ids for the categories needed for the link.  The categories are
	 * created if it doesn't exist.
	 *
	 * @param array $data
	 * @return array
	 */
	function create_or_get_link_categories($data)
	{
		$link_categories = array( 'link' => array(), 'cleanup' => array() );
		$categories = explode(',', $data['categories']);
		
		foreach( $categories as $category )
		{
			$c = trim($category);
			$cid = term_exists( $c, 'link_category' );
			if( !$cid )
			{
				$cid = wp_insert_term( $c, 'link_category' );
				$link_categories['cleanup'][] = $cid;
			}
			$link_categories['link'][] = $cid['term_id'];
		}
		
		return $link_categories;
	}



    /**
     * Return an array of category ids for a post.
     *
     * @param string  $data csv_post_categories cell contents
     * @param integer $common_parent_id common parent id for all categories
     * @return array category ids
     */
    function create_or_get_categories($data, $common_parent_id) {
        $ids = array(
            'post' => array(),
            'cleanup' => array(),
        );
        //$items = array_map('trim', explode(',', $data['csv_post_categories']));
        $items = array_map('trim', explode(',', $data));
        foreach ($items as $item) {
            if (is_numeric($item)) {
                if (get_category($item) !== null) {
                    $ids['post'][] = $item;
                } else {
                    $this->log['error'][] = "Category ID {$item} does not exist, skipping.";
                }
            } else {
                $parent_id = $common_parent_id;
                // item can be a single category name or a string such as
                // Parent > Child > Grandchild
                $categories = array_map('trim', explode('>', $item));
                if (count($categories) > 1 && is_numeric($categories[0])) {
                    $parent_id = $categories[0];
                    if (get_category($parent_id) !== null) {
                        // valid id, everything's ok
                        $categories = array_slice($categories, 1);
                    } else {
                        $this->log['error'][] = "Category ID {$parent_id} does not exist, skipping.";
                        continue;
                    }
                }
                foreach ($categories as $category) {
                    if ($category) {
                        $term = $this->term_exists($category, 'category', $parent_id);
                        if ($term) {
                            $term_id = $term['term_id'];
                        } else {
                            $term_id = wp_insert_category(array(
                                'cat_name' => $category,
                                'category_parent' => $parent_id,
                            ));
                            $ids['cleanup'][] = $term_id;
                        }
                        $parent_id = $term_id;
                    }
                }
                $ids['post'][] = $term_id;
            }
        }
        return $ids;
    }



	/**
	 * Inserts a string into another string at the offset.
	 *
	 * @param string $insert
	 * @param string $subject
	 * @param int $offset
	 * @param string
	 */
	function str_insert($insert, $subject, $offset)
	{
		return substr($subject, 0, $offset).$insert.substr($subject, $offset);
	}



	/**
	 * Determines if the first characters of the content is one of the search tags.
	 *
	 * @param string $content
	 * @param array $search_terms
	 * @return string|null
	 */
    function find_start_tag($content, $search_tags)
    {
    	$found_term = NULL;
   		
   		$content = str_replace( array("\n", "\r"), '', $content );
   		foreach( $search_tags as $term )
   		{
   			$tag = '<'.$term.'>';
   			if( substr($content, 0, strlen($tag)) == $tag )
   			{
   				$found_term = $term;
   				break;
   			}

   			$tag = '<'.$term.' ';
   			if( substr($content, 0, strlen($tag)) == $tag )
   			{
   				$found_term = $term;
   				break;
   			}
   		}
   		
   		return $found_term;
   	}



	/**
	 * Determines if the last characters of the content is one of the search tags.
	 *
	 * @param string $content
	 * @param array $search_terms
	 * @return string|null
	 */
    function find_end_tag($content, $search_tags)
    {
   		$found_term = NULL;
   		
   		$content = str_replace( array("\n", "\r"), '', $content );
   		foreach( $search_tags as $term )
   		{
   			$tag = '</'.$term.'>';
   			if( substr($content, strlen($content)-strlen($tag)) === $tag )
   			{
   				$found_term = $term;
   				break;
   			}
   		}
   		
   		return $found_term;
    }
    
    
    
	/**
     * Parse taxonomy data from the file
     *
     * array(
     *      // hierarchical taxonomy name => ID array
     *      'my taxonomy 1' => array(1, 2, 3, ...),
     *      // non-hierarchical taxonomy name => term names string
     *      'my taxonomy 2' => array('term1', 'term2', ...),
     * )
     *
     * @param array $data
     * @return array
     */
    private function get_taxonomies( $data )
    {
        $taxonomies = array();
        foreach ($data as $k => $v) 
        {
            if( preg_match('/^taxonomy-(.*)$/', $k, $matches) ) 
            {
                $tax_name = $matches[1];
               	$taxonomy = get_taxonomy( $tax_name );

				if( $taxonomy === false )
				{
                    $this->log['error'][] = "Unknown taxonomy: '$tax_name'";
                    continue;
				}
				
				$taxonomies[$tax_name] = $this->create_terms( $tax_name, $data[$k] );
            }
        }
        return $taxonomies;
    }



    /**
     * Return an array of term IDs for hierarchical taxonomies or the original
     * string from CSV for non-hierarchical taxonomies. The original string
     * should have the same format as csv_post_tags.
     *
     * @param string $taxonomy
     * @param string $field
     * @return mixed
     */
    private function create_terms( $taxonomy_name, $fields )
    {
 		$terms = array_map( 'trim', explode(',', $fields) );

		if( is_taxonomy_hierarchical($taxonomy_name) )
        {
            $term_ids = array();
            
            foreach( $terms as $term )
            {
		 		$heirarchy = array_map( 'trim', explode('>', $term) );
            	
            	$parent = null;
            	for( $i = 0; $i < count($heirarchy); $i++ )
            	{
            		if( !term_exists($heirarchy[$i], $taxonomy_name, $parent) )
            		{
            			$args = array();
            			if( $parent ) $args['parent'] = $parent;
            			
            			$result = wp_insert_term( $heirarchy[$i], $taxonomy_name, $args );
            			if( is_wp_error($result) )
            			{
            				$this->log['error'][] = 'Unable to insert '.$taxonomy_name.'term: '.$heirarchy[$i];
            				break;
            			}
            		}
            		
            		$term_object = get_term_by( 'name', $heirarchy[$i], $taxonomy_name );
            		if( is_wp_error($term_object) )
            		{
           				$this->log['error'][] = 'Invalid '.$taxonomy_name.'term: '.$heirarchy[$i];
           				break;
            		}
            		
            		$term_ids[] = $term_object->term_id;
            	}
            }
        
            return $term_ids;
        }

		return $terms;
    }


    function add_comments($post_id, $data) {
        // First get a list of the comments for this post
        $comments = array();
        foreach ($data as $k => $v) {
            // comments start with cvs_comment_
            if (    preg_match('/^csv_comment_([^_]+)_(.*)/', $k, $matches) &&
                    $v != '') {
                $comments[$matches[1]] = 1;
            }
        }
        // Sort this list which specifies the order they are inserted, in case
        // that matters somewhere
        ksort($comments);

        // Now go through each comment and insert it. More fields are possible
        // in principle (see docu of wp_insert_comment), but I didn't have data
        // for them so I didn't test them, so I didn't include them.
        $count = 0;
        foreach ($comments as $cid => $v) {
            $new_comment = array(
                'comment_post_ID' => $post_id,
                'comment_approved' => 1,
            );

            if (isset($data["csv_comment_{$cid}_author"])) {
                $new_comment['comment_author'] = convert_chars(
                    $data["csv_comment_{$cid}_author"]);
            }
            if (isset($data["csv_comment_{$cid}_author_email"])) {
                $new_comment['comment_author_email'] = convert_chars(
                    $data["csv_comment_{$cid}_author_email"]);
            }
            if (isset($data["csv_comment_{$cid}_url"])) {
                $new_comment['comment_author_url'] = convert_chars(
                    $data["csv_comment_{$cid}_url"]);
            }
            if (isset($data["csv_comment_{$cid}_content"])) {
                $new_comment['comment_content'] = convert_chars(
                    $data["csv_comment_{$cid}_content"]);
            }
            if (isset($data["csv_comment_{$cid}_date"])) {
                $new_comment['comment_date'] = $this->parse_date(
                    $data["csv_comment_{$cid}_date"]);
            }

            $id = wp_insert_comment($new_comment);
            if ($id) {
                $count++;
            } else {
                $this->log['error'][] = "Could not add comment $cid";
            }
        }
        return $count;
    }



    function create_custom_fields($post_id, $data) {
        foreach ($data as $k => $v) {
            // anything that doesn't start with csv_ is a custom field
            if (!preg_match('/^csv_/', $k) && $v != '') {
                add_post_meta($post_id, $k, $v);
            }
        }
    }



    function get_auth_id($author) {
        if (is_numeric($author)) {
            return $author;
        }
        $author_data = get_userdatabylogin($author);
        return ($author_data) ? $author_data->ID : 0;
    }



    /**
     * Convert date in CSV file to 1999-12-31 23:52:00 format
     *
     * @param string $data
     * @return string
     */
    function parse_date($data) {
        $timestamp = strtotime($data);
        if (false === $timestamp) {
            return '';
        } else {
            return date('Y-m-d H:i:s', $timestamp);
        }
    }



    /**
     * Delete BOM from UTF-8 file.
     *
     * @param string $fname
     * @return void
     */
    function stripBOM($fname) {
        $res = fopen($fname, 'rb');
        if (false !== $res) {
            $bytes = fread($res, 3);
            if ($bytes == pack('CCC', 0xef, 0xbb, 0xbf)) {
                $this->log['notice'][] = 'Getting rid of byte order mark...';
                fclose($res);

                $contents = file_get_contents($fname);
                if (false === $contents) {
                    trigger_error('Failed to get file contents.', E_USER_WARNING);
                }
                $contents = substr($contents, 3);
                $success = file_put_contents($fname, $contents);
                if (false === $success) {
                    trigger_error('Failed to put file contents.', E_USER_WARNING);
                }
            } else {
                fclose($res);
            }
        } else {
            $this->log['error'][] = 'Failed to open file, aborting.';
        }
    }
}

?>
