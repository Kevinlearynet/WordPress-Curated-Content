<?php get_header(); ?>

<div class="category-title">
	<div class="inner clearfix">
		<h1>Hand Picked Resources for Web Professionals</h1>
		<p class="desc">The best web design resources of the week, gathered from around the web.</p>
	</div>
</div>

<div class="wrapper curated clearfix">

<?php if ( have_posts() ) : ?>

	<div class="loading">
		<div class="center-pipe"></div>
		<div class="inside">
			<span>Loading&hellip;</span>
			<div class="ring">
				<div class="holder">
					<div class="ball"></div>
				</div>
			</div>
		</div>
	</div>
	
	<?php if ( !is_paged() ): ?>
	<div class="signup">
		<form action="http://kevinleary.us2.list-manage1.com/subscribe/post?u=fdd3d040d9923af80b9ec18d6&amp;id=6e9bcc9c48" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" target="_blank" novalidate>
			<p>Strapped for time? Get these delivered to your inbox each week</p>
			<input type="email" value="Email Address" name="EMAIL" class="required email valueFx" id="mce-EMAIL">
			<input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="pill-button">
			<a href="http://www.kevinleary.net/curated-rss-feed/" class="rss-icon"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/orange-rss.png" alt="Subscribe to RSS" /></a>
			<input type="hidden" value="html" name="EMAILTYPE" id="mce-EMAILTYPE-0">
		</form>
	</div>
	<?php endif; ?>

	<div class="timeline clearfix">
		<div class="center-pipe"></div>
		
<?php 
// Custom posts per page
global $wp_query;
$wp_query->query_vars['posts_per_page'] = 20;
query_posts($wp_query->query_vars);

// The loop
while ( have_posts() ) : the_post();

// Get custom field data & santize
$custom_fields = get_post_custom();
$canonical = ( !empty($custom_fields['_curated_redirect'][0]) ) ? esc_url($custom_fields['_curated_redirect'][0]) : get_permalink();
$author = ( !empty($custom_fields['_curated_author'][0]) ) ? esc_attr($custom_fields['_curated_author'][0]) : 'Anonymous Contributor';
$image = ( !empty($custom_fields['_curated_image'][0]) ) ? esc_url($custom_fields['_curated_image'][0]) : null;
$site_url = ( !empty($custom_fields['_curated_url'][0]) ) ? esc_url($custom_fields['_curated_url'][0]) : get_permalink();
$website = ( !empty($custom_fields['_curated_website'][0]) ) ? esc_attr($custom_fields['_curated_website'][0]) : '';
$human_time = human_time_diff( get_the_time('U'), current_time('timestamp') ) . ' ago';

// Cleanup website titles, run through special cases
global $curated_special_cases;
$match = false;
foreach ( $curated_special_cases as $case ) {
	if ( strpos($website, $case) !== false ) {
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

// Define class
$class = ( $image ) ? 'article has-image' : 'article';
$excerpt = _k_get_curated_excerpt();
?>
		<div <?php post_class('post content'); ?>>
			<div class="pointer"></div>
			
			<div class="<?php echo $class; ?>">
				<h2 class="post-title"><a href="<?php echo $canonical; ?>" rel="bookmark" class="outbound"><?php the_title(); ?></a></h2>
				
				<div class="post-info">
					<p><?php echo $human_time; ?><em>by</em><?php echo $author; ?><em>at</em><a href="<?php echo $site_url; ?>"><?php echo $website_title; ?></a><?php edit_post_link( __(" | Edit"), '' ); ?></p>
				</div><!--// end .post-info -->
				
				<?php
				if ( function_exists('base_social_sharing_widgets') ) {
					base_social_sharing_widgets( null, array(
						'title' => get_the_title(),
						'link' => $canonical,
					) );
				}
				?>
				
				<div class="entry">
					<?php if ( $image ): ?>
					<a href="<?php echo $canonical; ?>" class="feature-image outbound">
						<img src="<?php echo get_stylesheet_directory_uri() . "/inc/timthumb.php?src=$image&w=451"; ?>" />
					</a>
					<?php elseif ( has_post_thumbnail() ): ?>
					<a href="<?php echo $canonical; ?>" class="feature-image outbound">
						<?php the_post_thumbnail(); ?>
					</a>
					<?php endif; ?>
					
					<?php 
					add_filter('the_excerpt', array($GLOBALS['wp_embed'], 'autoembed'), 9);
					if ( $excerpt ) _e( $excerpt );
					?>
				</div>
			</div><!--// end .article -->
		</div><!--// end #post-XX -->
	
<?php endwhile; ?>

	</div><!--// end .timeline -->

	<?php if ( function_exists('base_pagination') ) { base_pagination($wp_query); } else if ( is_paged() ) { ?>
	<div class="navigation clearfix">
		<div class="alignleft"><?php next_posts_link('&laquo; Previous Entries') ?></div>
		<div class="alignright"><?php previous_posts_link('Next Entries &raquo;') ?></div>
	</div>
	<?php } ?>
			
<?php else : ?>

	<div class="no-content entry">
		<h2>More curated goodness is on it's way!</h2>
		<p>Be patient, I'm working on finding more of the best resources for you.</p>
	</div>

<?php endif; wp_reset_query(); ?>

<?php get_footer(); ?>
