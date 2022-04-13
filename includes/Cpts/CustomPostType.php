<?php

namespace PagarmeSplitPayment\Cpts;

use \Carbon_Fields\Container\Container;

class CustomPostType {
    public $name, $singularName, $slug, $fields;

    public function __construct($name, $singularName, $slug, $fields = [], $external = false)
    {
        $this->name = $name;
        $this->singularName = $singularName;
        $this->slug = $slug;
        $this->fields = $fields;
        $this->external = $external;
    }

    public function register()
    {
        register_post_type( 
            $this->slug,
            array(
                'labels' => array(
                    'name' => __( $this->name ),
                    'singular_name' => __( $this->singularName )
                ),
                'public' => true,
                'has_archive' => true,
                'rewrite' => array('slug' => $this->slug),
                'show_in_rest' => true,
            )
        );
    }

    public function addFields()
    {
        Container::make( 
            'post_meta', 
            __(PLUGIN_NAME . " - {$this->singularName} Data")
        )
        ->where( 'post_type', '=', $this->slug )
        ->add_fields( $this->fields );
    }

    public function create()
    {
        if (!$this->external) {
            add_action( 'init', array($this, 'register') );
        }
        add_action( 'carbon_fields_register_fields', array($this, 'addFields') );
    }
}
