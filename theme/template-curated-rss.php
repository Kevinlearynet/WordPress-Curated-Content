<?php
/**
 * Template Name: Newsletter RSS Feed
 *
 * Custom RSS feed template for the curated newsletter import into
 * a mailchimp RSS email campaign.
 */
function curated_rss_date( $timestamp = null ) {
	$timestamp = ( $timestamp == null ) ? time() : $timestamp;
	echo date( DATE_RSS, $timestamp );
}

// Settings
$posts = query_posts( 'showposts=10&post_type=curated' );
$lastpost = $numposts - 1;

// Set doc headers to identify type
header("Content-Type: application/rss+xml; charset=UTF-8");
echo '<?xml version="1.0"?>';
?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>Weekly Web Design Resources &amp; Inspiration</title>
		<link>http://www.kevinleary.net/curated/</link>
		<description>A weekly source of web design and development inspiration, resources and news.</description>
		<atom:link href="http://www.kevinleary.net/curated-rss-feed/" rel="self" type="application/rss+xml" />
		<image>
			<title>Weekly Web Design Resources &amp; Inspiration</title>
			<url>http://www.kevinleary.net/wp-content/themes/kevinleary/images/kevin-leary-logo.png</url>
			<link>http://www.kevinleary.net/curated/</link>
		</image>
		<language>en-us</language>
		<pubDate><?php curated_rss_date( strtotime( $ps[$lastpost]->post_date_gmt ) ); ?></pubDate>
		<lastBuildDate><?php curated_rss_date( strtotime( $ps[$lastpost]->post_date_gmt ) ); ?></lastBuildDate>
		<managingEditor>info@kevinleary.net (Kevin Leary)</managingEditor>
<?php 
// The Loop
foreach ( $posts as $post ):

// Setup postdata
setup_postdata( $post );
add_filter('the_excerpt', array($GLOBALS['wp_embed'], 'autoembed'), 9);

// Get custom field data & santize
$custom_fields = get_post_custom( $post->ID );
$canonical = ( !empty($custom_fields['_curated_redirect'][0]) ) ? esc_url($custom_fields['_curated_redirect'][0]) : get_permalink();
$author = ( !empty($custom_fields['_curated_author'][0]) ) ? esc_attr($custom_fields['_curated_author'][0]) : 'Anonymous Contributor';
$image = ( !empty($custom_fields['_curated_image'][0]) ) ? esc_url($custom_fields['_curated_image'][0]) : null;
$site_url = ( !empty($custom_fields['_curated_url'][0]) ) ? esc_url($custom_fields['_curated_url'][0]) : get_permalink();
$website = ( !empty($custom_fields['_curated_website'][0]) ) ? esc_attr($custom_fields['_curated_website'][0]) : '';
$human_time = human_time_diff( get_the_time('U'), current_time('timestamp') ) . ' ago';

// Fix special cases
global $curated_special_cases;
$match = false;
foreach ( $curated_special_cases as $case ) {
	if ( strpos( $website, $case ) !== false ) {
		$website_title = $case;
		$match = true;
		break;
	}
}

// No special case match
if ( !$match ) {
	$website_title = explode( " ", trim($website) );
	$website_title = $website_title[0];
}

// Get excerpt
$excerpt = _k_get_curated_excerpt();
?>
		<item>
			<title><?php echo get_the_title($post->ID); ?></title>
			<link><?php echo get_permalink($post->ID); ?></link>
			<description><![CDATA[
			<?php if ( $image ): ?>
			<a href="<?php echo get_permalink($post->ID); ?>" class="feature-image"><img src="<?php echo get_stylesheet_directory_uri() . "/inc/timthumb.php?src=$image&w=500&h=278&a=t"; ?>" /></a>
			<?php elseif ( has_post_thumbnail() ): ?>
			<a href="<?php echo get_permalink($post->ID); ?>" class="feature-image"><?php the_post_thumbnail(); ?></a>
			<?php endif; ?>
			<?php _e($excerpt); ?>
			<p style="margin-bottom:0;"><strong>Contributed by <?php echo $author; ?> at <a href="<?php echo $site_url; ?>"><?php echo $website_title; ?></a></strong></p>]]></description>
			<pubDate><?php curated_rss_date( strtotime($post->post_date_gmt) ); ?></pubDate>
			<guid><?php echo get_permalink( $post->ID ); ?></guid>
		</item>
<?php 
endforeach; wp_reset_postdata();
?>
	</channel>
</rss>
