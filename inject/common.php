<?php

namespace JSON_LD_Author_Plugin;

/**
 * Finds all values in a multidimensional array that match a test callback.
 * The results are all by reference.
 */
function find_all_in_multidimensional_array( array &$haystack, Callable $test_callback, &$results): int {
  if ( !is_array($haystack) ) {
    throw new \InvalidArgumentException('$haystack must be an array');
  }
  $stack = [&$haystack];
  $results = [];

  while (!empty($stack)) {
      end($stack);
      $current = &$stack[key($stack)];
      array_pop($stack);

      foreach ($current as $key => &$value) {
          $is_match = call_user_func($test_callback, $key, $value);
          if ($is_match) {
              $results[] = &$current[$key];
          } else if (is_array($value)) {
              $stack[] = &$value; // Add nested array to the stack
          }
      }
  }

  return count($results);
}

/**
 * Gets the WordPress User ID from the JSON-LD `@id` value for a Person.
 */
function get_user_id_from_person_id( string $person_id ) {
  static $is_rewrite_enabled = null;
  static $url_regex = null;
  $author_name = null;
  $author_id = null;

  if ( $is_rewrite_enabled === null ) {
    $is_rewrite_enabled = boolval(get_option( 'rewrite_rules' ));
  }

  if ( $is_rewrite_enabled ) {
    if ( $url_regex === null ) {
      $url = get_author_posts_url( 9876543210, '%author_nicename%' );
      $url_regex = '/^' . preg_quote($url, '/') . '/';
      $url_regex = str_replace('%author_nicename%', '(?P<author_name>[^\\/#?]+)', $url_regex);
    }

    preg_match($url_regex, $person_id, $matches);
    $author_name = key_exists('author_name', $matches) ? $matches['author_name'] : null;
    
  } else {
    $query_string = parse_url( $person_id, PHP_URL_QUERY );
    parse_str($query_string, $query_vars);
    $author_name = key_exists('author_name', $query_vars) ? $query_vars['author_name'] : null;
    $author_id = key_exists('author', $query_vars) ? $query_vars['author'] : null;
  }

  if ( $author_id ) {
    return intval( $author_id, 10 );
  }

  if ( $author_name ) {
    $user = \get_user_by('slug', $author_name);
    if ( $user ) {
      return $user->ID;
    }
  }

  return null;
}

/**
 * Gets the custom Person schema for the WordPress user.
 */
function get_person_schema_for_user( $user_id ) {
  $schema = \get_user_meta( $user_id, 'person_schema', true );

  if ( empty($schema) ) {
    return array();
  }

  $schema = json_decode($schema, true);

  if ( !is_array($schema) ) {
    return array();
  }

  return $schema;
}

function append_schema( &$schema, $append ) {
  foreach ( $append as $key => $value ) {
    if ( !key_exists($key, $schema) ) {
      $schema[$key] = $value;
      continue;
    }

    if ( is_array($schema[$key]) && is_array($value) ) {
      append_schema($schema[$key], $value);
      continue;
    }

    $schema[$key] = $value;
  }
}