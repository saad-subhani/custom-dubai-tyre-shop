<?php
/**
 * Plugin Name: Home Offer Slider
 * Description: Manage and display homepage offer cards.
 * Version: 1.0.0
 * Author: Custom Dubai Tyre Shop
 */

defined( 'ABSPATH' ) || exit;

function hos_get_offers() {
	$offers = get_option( 'hos_offers', array() );
	return is_array( $offers ) ? $offers : array();
}

function hos_admin_menu() {
	add_menu_page( 'Home Offer Slider', 'Home Offers', 'manage_options', 'home-offer-slider', 'hos_admin_page', 'dashicons-images-alt2', 59 );
}
add_action( 'admin_menu', 'hos_admin_menu' );

function hos_admin_assets( $hook ) {
	if ( 'toplevel_page_home-offer-slider' !== $hook ) return;
	wp_enqueue_media();
	wp_add_inline_script( 'jquery', "jQuery(function(\$){var list=\$('#hos-offers');\$('#hos-add').on('click',function(){list.append(\$('#hos-template').html().replace(/__i__/g,list.children().length));});list.on('click','.hos-remove',function(){\$(this).closest('.hos-row').remove();});list.on('input','.hos-image-url',function(){\$(this).closest('.hos-row').find('.hos-image-id').val('0');});list.on('click','.hos-media',function(e){e.preventDefault();var row=\$(this).closest('.hos-row'),frame=wp.media({title:'Choose offer image',button:{text:'Use image'},multiple:false});frame.on('select',function(){var image=frame.state().get('selection').first().toJSON();row.find('.hos-image-id').val(image.id);row.find('.hos-image-url').val(image.url);row.find('.hos-preview').attr('src',image.url).show();});frame.open();});});" );
}
add_action( 'admin_enqueue_scripts', 'hos_admin_assets' );

function hos_save_offers() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to manage offers.' );
	check_admin_referer( 'hos_save_offers' );
	$input = isset( $_POST['hos_offers'] ) ? wp_unslash( $_POST['hos_offers'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$offers = array();
	foreach ( (array) $input as $offer ) {
		$title = sanitize_text_field( $offer['title'] ?? '' );
		$image_id = absint( $offer['image_id'] ?? 0 );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : esc_url_raw( $offer['image_url'] ?? '' );
		if ( '' === $title && '' === $image_url ) continue;
		$offers[] = array(
			'title' => $title,
			'image_id' => $image_id,
			'image_url' => $image_url,
			'button_text' => sanitize_text_field( $offer['button_text'] ?? 'Shop now' ),
			'button_link' => esc_url_raw( $offer['button_link'] ?? '' ),
		);
	}
	update_option( 'hos_offers', $offers );
	wp_safe_redirect( add_query_arg( array( 'page' => 'home-offer-slider', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_hos_save_offers', 'hos_save_offers' );

function hos_render_offer_row( $index, $offer = array() ) {
	$image = $offer['image_url'] ?? '';
	?>
	<div class="hos-row" style="background:#fff;border:1px solid #c3c4c7;margin:16px 0;padding:16px;max-width:900px">
		<h2>Offer <?php echo esc_html( is_numeric( $index ) ? (string) ( $index + 1 ) : '' ); ?> <button type="button" class="button-link-delete hos-remove">Remove</button></h2>
		<p><label>Heading<br><input class="regular-text" name="hos_offers[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $offer['title'] ?? '' ); ?>"></label></p>
		<p><label>Image URL<br><input class="large-text hos-image-url" name="hos_offers[<?php echo esc_attr( $index ); ?>][image_url]" value="<?php echo esc_url( $image ); ?>"></label></p>
		<input type="hidden" class="hos-image-id" name="hos_offers[<?php echo esc_attr( $index ); ?>][image_id]" value="<?php echo esc_attr( $offer['image_id'] ?? 0 ); ?>">
		<button type="button" class="button hos-media">Choose from Media Library</button>
		<img class="hos-preview" src="<?php echo esc_url( $image ); ?>" alt="" style="<?php echo $image ? 'display:block;' : 'display:none;'; ?>max-width:240px;max-height:100px;object-fit:cover;margin-top:10px">
		<p><label>Button text<br><input class="regular-text" name="hos_offers[<?php echo esc_attr( $index ); ?>][button_text]" value="<?php echo esc_attr( $offer['button_text'] ?? 'Shop now' ); ?>"></label></p>
		<p><label>Button link<br><input class="large-text" type="url" name="hos_offers[<?php echo esc_attr( $index ); ?>][button_link]" value="<?php echo esc_url( $offer['button_link'] ?? '' ); ?>"></label></p>
	</div>
	<?php
}

function hos_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$offers = hos_get_offers();
	?>
	<div class="wrap"><h1>Home Offer Slider</h1><p>Offers display four across on desktop and scroll when more than four are added.</p>
	<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Offers saved.</p></div><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="hos_save_offers"><?php wp_nonce_field( 'hos_save_offers' ); ?>
	<div id="hos-offers"><?php foreach ( $offers as $i => $offer ) hos_render_offer_row( $i, $offer ); ?></div>
	<p><button type="button" class="button" id="hos-add">Add offer</button></p><?php submit_button( 'Save Offers' ); ?></form>
	<template id="hos-template"><?php hos_render_offer_row( '__i__', array() ); ?></template></div>
	<?php
}

function hos_render_homepage() {
	$offers = array_values( array_filter( hos_get_offers(), function( $offer ) { return ! empty( $offer['image_url'] ); } ) );
	if ( ! $offers ) return;
	foreach ( $offers as &$offer ) {
		$offer['title'] = $offer['title'] ?? '';
		$offer['button_text'] = $offer['button_text'] ?? 'Shop now';
		$offer['button_link'] = $offer['button_link'] ?? '#';
	}
	unset( $offer );
	echo '<div class="hos-react-root" data-offers="' . esc_attr( wp_json_encode( $offers ) ) . '"></div>';
}

function hos_frontend_assets() {
	if ( ! is_front_page() && ! is_page_template( 'front-page.php' ) ) return;
	wp_enqueue_script( 'react' );
	wp_enqueue_script( 'react-dom' );
	$css = '.hos-section{max-width:var(--container-max-width,1160px);margin:var(--space-12,48px) auto;padding:0 var(--space-4,16px);font-family:var(--font-family-base, sans-serif);color:var(--color-text,#222)}.hos-heading{font:var(--font-weight-bold,700) var(--font-size-section-heading,32px)/var(--line-height-section-heading,1.08) var(--font-family-heading,sans-serif);text-align:center;margin:0 0 var(--space-6,24px)}.hos-track-wrap{overflow:hidden}.hos-track{display:flex;gap:var(--space-6,24px);transition:transform var(--motion-duration-normal,300ms) ease;will-change:transform}.hos-card{box-sizing:border-box;flex:0 0 calc((100% - 72px)/4);min-width:0;overflow:hidden;border:var(--border-width,1px) solid var(--color-border,#d9dde1);border-radius:20px;background:var(--color-background,#fff);display:flex;flex-direction:column}.hos-card-heading{height:68px;box-sizing:border-box;padding:var(--space-3,12px) var(--space-4,16px);display:flex;align-items:center;justify-content:space-between;gap:8px}.hos-card-heading h3{font:var(--font-weight-semibold,600) var(--font-size-md,18px)/1.2 var(--font-family-heading,sans-serif);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0}.hos-card-heading>a{flex:none;border:var(--border-width,1px) solid var(--color-primary,#222);border-radius:999px;padding:9px 22px;color:var(--color-primary,#222);font-size:var(--font-size-sm,14px);font-weight:var(--font-weight-semibold,600);text-decoration:none}.hos-image-link{display:block;aspect-ratio:1.8;background:var(--color-background-alt,#f3f4f5);overflow:hidden}.hos-image-link img{width:100%;height:100%;object-fit:cover;display:block}.hos-cta{display:flex;min-height:52px;align-items:center;justify-content:center;background:var(--color-primary,#222);color:var(--color-secondary,#fff);font-weight:var(--font-weight-bold,700);font-size:var(--font-size-base,16px);text-transform:uppercase;text-decoration:none}.hos-cta:hover{background:var(--color-cta-hover,var(--color-primary,#222));color:var(--color-secondary,#fff)}.hos-controls{display:none;justify-content:flex-end;gap:8px;margin-top:14px}.hos-controls button{width:38px;height:38px;border:1px solid var(--color-border,#ddd);border-radius:50%;background:var(--color-background,#fff);color:var(--color-primary,#222);font-size:20px;cursor:pointer}.hos-section.is-slider .hos-controls{display:flex}@media(max-width:900px){.hos-card{flex-basis:calc((100% - 24px)/2)}}@media(max-width:560px){.hos-track{gap:var(--space-4,16px)}.hos-card{flex-basis:86%}.hos-card-heading{height:60px}.hos-section{margin:var(--space-8,32px) auto}}';
	wp_register_style( 'home-offer-slider', false, array( 'custom-dubai-theme' ), '1.0.0' );
	wp_enqueue_style( 'home-offer-slider' );
	wp_add_inline_style( 'home-offer-slider', $css );
	$js = "(function(){var e=React.createElement;function Slider(p){var ref=React.useRef(null),state=React.useState(0),index=state[0],setIndex=state[1],size=React.useState(4),visible=size[0],offers=p.offers;React.useEffect(function(){function measure(){var el=ref.current;if(!el)return;var card=el.querySelector('.hos-card');if(!card)return;var gap=parseFloat(getComputedStyle(el.querySelector('.hos-track')).columnGap)||0,count=Math.max(1,Math.floor((el.clientWidth+gap)/(card.getBoundingClientRect().width+gap)));setSize(count);setIndex(function(n){return Math.min(n,Math.max(0,offers.length-count))})}measure();window.addEventListener('resize',measure);return function(){window.removeEventListener('resize',measure)}},[offers.length]);var card=ref.current&&ref.current.querySelector('.hos-card'),gap=ref.current?parseFloat(getComputedStyle(ref.current.querySelector('.hos-track')).columnGap)||0:0,step=card?card.getBoundingClientRect().width+gap:0;return e('section',{className:'hos-section'+(offers.length>visible?' is-slider':''),'aria-label':'Current deals'},e('h2',{className:'hos-heading'},'Current Deals'),e('div',{className:'hos-track-wrap',ref:ref},e('div',{className:'hos-track',style:{transform:'translateX(-'+(index*step)+'px)'}},offers.map(function(o,i){return e('article',{className:'hos-card',key:i},e('div',{className:'hos-card-heading'},e('h3',null,o.title),e('a',{href:o.button_link||'#'},'Details')),e('a',{className:'hos-image-link',href:o.button_link||'#'},e('img',{src:o.image_url,alt:o.title||'',loading:'lazy'})),e('a',{className:'hos-cta',href:o.button_link||'#'},o.button_text||'Shop now'))}))),e('div',{className:'hos-controls','aria-label':'Offer slider controls'},e('button',{type:'button','aria-label':'Previous offers',onClick:function(){setIndex(Math.max(0,index-visible))}},'←'),e('button',{type:'button','aria-label':'Next offers',onClick:function(){setIndex(Math.min(Math.max(0,offers.length-visible),index+visible))}},'→')))}document.querySelectorAll('.hos-react-root').forEach(function(root){try{ReactDOM.createRoot(root).render(e(Slider,{offers:JSON.parse(root.dataset.offers||'[]')}))}catch(err){console.error('Home offers could not be rendered',err)}})})();";
	wp_add_inline_script( 'react-dom', $js, 'after' );
}
add_action( 'wp_enqueue_scripts', 'hos_frontend_assets', 120 );
