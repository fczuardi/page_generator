<?php
/*
Plugin Name: Page Generator
Plugin URI: https://github.com/fczuardi/page_generator
Description: Generates/updates an initial set of pages and subpages based on a folder containing html files.
Version: 0.2
Author: Fabricio Campos Zuardi
Author URI: http://fabricio.org
License: New BSD
*/

/*
New BSD License:

Redistribution and use in source and binary forms, with or without 
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, 
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright 
      notice, this list of conditions and the following disclaimer in the 
      documentation and/or other materials provided with the distribution.
    * The name of the author may not be used to endorse or promote products 
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR IMPLIED 
WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF 
MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO 
EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, 
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, 
PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, 
WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR 
OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF 
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

add_action('admin_menu', 'add_pages_menuentry');

function add_pages_menuentry() {
    add_pages_page(__('Initial Page Set','page_generator'), __('Initial Page Set','page_generator'), 'manage_options', 'startingpages', 'initial_page_set_settings');
}

function initial_page_set_settings() {
    echo "<h2>" . __( 'Default Static Pages', 'page_generator' ) . "</h2>";
    if (!current_user_can('manage_options')){
      wp_die( __('You do not have sufficient permissions to access this page.') );
    }
    $submit_label = __( 'Populate Initial Pages', 'page_generator' );
    $has_pages = false;
    $content_pages_path = WP_CONTENT_DIR . '/initial_pages';
    $pages_by_depth = array();
    //loop through the content directory
    if ($handle = opendir($content_pages_path)) {
        $page_ids = array();
        /* This is the correct way to loop over the directory. */
        while (false !== ($file = readdir($handle))) {
          $name_parts = explode('.', $file);
          if ($name_parts[1] == '') { continue; }
          $path_parts = array_slice($name_parts, 1, -1);
          $depth = count($path_parts) -1;
          $page_path = implode('/', $path_parts);
          if (! isset($pages_by_depth[$depth])){
            $pages_by_depth[$depth] = array();
          }
          echo $page_path;
          echo '<br>';
          $pages_by_depth[$depth][] = $file;
        }
        if ($_POST['update_pages'] == 'yes'){
          foreach ($pages_by_depth as $depth => $pages){
            foreach ($pages as $file){
              $page_content = file_get_contents($content_pages_path . '/' . $file);
              $name_parts = explode('.', $file);
              $path_parts = array_slice($name_parts, 1, -1);
              $page_path = implode('/', $path_parts);
              $page = get_page_by_path($page_path);

              //extract metadata
              preg_match("/^\<!--([^∫]*)-->/U", $page_content, $matches);
              $meta_lines = explode("\n", trim($matches[1]));
              $meta = array();
              foreach ($meta_lines as $line){
                preg_match("/([^∫]*)\s*\:\s*(.*)/", $line, $parts);
                $meta[strtolower($parts[1])] = $parts[2];
              }
              if (is_null($page)){
                $updated_page = array();
                $updated_page['post_type']  = 'page';
                $updated_page['post_name'] = $name_parts[count($name_parts)-2];
              } else {
                $updated_page = (array) $page;
                $has_pages = true;
              }
              $updated_page['post_title'] = $meta['title'];
              $updated_page['post_status'] = 'publish';
              $updated_page['post_content'] = substr($page_content,strlen($matches[0])+1);
              $updated_page['menu_order'] = (int) $name_parts[0]; //If new post is a page, sets the order should it appear in the tabs.
              if (count($name_parts) >=4 ){
                // is child
                $parent_path = implode('/', array_slice($path_parts,0,-1));
                $updated_page['post_parent'] = $page_ids[$parent_path];
              }
              $updated_page['page_path'] = $page_path;
              if (is_null($page)){
                // insert
                $pageid = wp_insert_post($updated_page);
                if ($pageid == 0) { 
                 echo  'Add Page Failed <br>';
                }
              } else {
                //updated
                $pageid = $updated_page['ID'];
                wp_update_post($updated_page);
              }
              $page_ids[$page_path] = $pageid;
              if ($meta['template']) {
                update_post_meta($pageid, '_wp_page_template', $meta['template'] . '.php');
              }
              if ($meta['option']) {
                if (($meta['option'] == 'page_on_front') || ($meta['option'] == 'page_for_posts')){
                  update_option( $meta['option'], $pageid );
                }
              }
            } //foreach pages
          }// foreach pages_by_depth
        }
        closedir($handle);
        if ($has_pages) {
          $submit_label = __( 'Sobrescrever Páginas Iniciais (cuidado!)', 'pgsm-boilerplate-child' );
        }
        ?>
        <form action="" method="POST">
          <input type="hidden" name="update_pages" value="yes">
          <input type="submit" value="<?php echo $submit_label;?>"/>
        <?php
    }    
}

?>