<?php
use CodesWholesale\Client;
use CodesWholesale\CodesWholesale;
use CodesWholesale\Exceptions\NoImagesFoundException;
use CodesWholesale\Resource\Product;
use CodesWholesale\Resource\ImageType;
use CodesWholesale\Resource\FullProduct;
use CodesWholesale\Resource\StockAndPriceChange;
use CodesWholesale\Resource\ProductDescription;
use CodesWholesaleFramework\Model\ProductModel;
use CodesWholesaleFramework\Factories\ProductModelFactory;
use CodesWholesaleFramework\Postback\UpdateProduct\UpdateProductInterface;

		function getSpreadParams() {

			$options = CW()->instance()->get_options();
			$spread_type = $options['spread_type'];
			$spread_value = $options['spread_value'];
	
			$spread_params = array(
				'cwSpreadType' => $spread_type,
				'cwSpread' => $spread_value
			);
	
			return $spread_params;
		}
		
		function calculateSpread(array $spreadParams, $price)
		{
			if ($spreadParams['cwSpreadType'] == 0) {
				if ($price != '') {
					$priceSpread = $price + $spreadParams['cwSpread'];
				} else {
					$priceSpread = '';
				}	
			} else if ($spreadParams['cwSpreadType'] == 1) {
				if ($price != '') {
					$result = $price / 100 * $spreadParams['cwSpread'] + $price;
					$priceSpread = round($result, 2);
				} else {
					$priceSpread = '';
				}
			}	
			return $priceSpread;
		}

		function cron_job()
        {
				$products_ids = array();

				$cw_products = array();

				$params = [
					/**
					 * API Keys
					 * These are test api keys that can be used for testing your integration:
					 */
					'cw.client_id' => 'bdf304bf3970bdaeed75fd0425775921',
					'cw.client_secret' => '$2a$10$PR6gyBIn30VJdAeLhQjS8ep59PytfgW13Mge5rILMCaJh.Wmph19e',
					/**
					 * CodesWholesale ENDPOINT
					*/
					'cw.endpoint_uri' => CodesWholesale::LIVE_ENDPOINT,
					/**
					 * Due to security reasons, you should use SessionStorage only while testing.
					* In order to go live, you should change it to database storage.
					*/
					'cw.token_storage' => new \CodesWholesale\Storage\TokenSessionStorage()
				];
				
				
				$clientBuilder = new \CodesWholesale\ClientBuilder($params);
				$client = $clientBuilder->build();
			
				$cw_products = $client->getProducts();
			
				$cw_products_names_array = array();

				$cw_product_id = array();

				$cw_product_ids = array();
				
				foreach ($cw_products as $cw_product) {
					$cw_products_names_array[] =  $cw_product->getProductId();
				}

				$products = get_posts(array(
					'post_type' => 'product',
					'post_status' => 'any',
					'meta_query' => array(
						array(
							'key' => CodesWholesaleConst::PRODUCT_CODESWHOLESALE_ID_PROP_NAME,
							'value' => '',
							'compare' => '!='
						)
					),
					'numberposts' => -1
				));
				
				$ids_pre_order = 0;
				$products_ids_pre_order = array();
				foreach ($products as $product) {
					$cw_product_id = get_post_meta($product->ID, CodesWholesaleConst::PRODUCT_CODESWHOLESALE_ID_PROP_NAME, true);
					$cw_product_ids[] = get_post_meta($product->ID, CodesWholesaleConst::PRODUCT_CODESWHOLESALE_ID_PROP_NAME, true);
					$products_ids[$cw_product_id] = $product;
					$availability_datetime = (int) get_post_meta( $product->ID, "_wc_pre_orders_availability_datetime", true );
					$availability_timestamp_in_utc_plus_five = $availability_datetime + 300;
					$availability_timestamp_in_utc_neg_five = $availability_datetime - 300;
					if ( strtotime("now") < $availability_timestamp_in_utc_plus_five && strtotime("now") > $availability_timestamp_in_utc_neg_five ) {
						$products_ids_pre_order[$ids_pre_order] = $cw_product_id;
						$ids_pre_order++;
					}
				}
			
				/* $cw_products_not_set = array_diff($cw_products_names_array, $cw_product_ids);
				$cw_products_no_more = array_diff($cw_product_ids, $cw_products_names_array); */

				$x = 0;
				
				$cw_products_count = count($cw_products);

				$cw_products_names_array_rands = array_rand($cw_products_names_array, 100);

				foreach ($cw_products_names_array_rands as $cw_products_names_array_rand){
					$cw_products_arrays[] = $cw_products_names_array[$cw_products_names_array_rand];
				}

				$cw_products_not_sets = array_diff($cw_products_names_array, $cw_product_ids);

				print_r($cw_products_not_sets);
				
				foreach ($cw_products as $cw_product) {
					if (in_array($cw_product->getProductId(), $cw_products_arrays) || in_array($cw_product->getProductId(), $cw_products_not_sets) || in_array($cw_product->getProductId(), $products_ids_pre_order)) {
						echo $x."/".$cw_products_count."\n";
							echo $cw_product->getName()." - ".$cw_product->getProductId()."\n";
							if (!isset($products_ids[$cw_product->getProductId()])) {
							
								echo "Adding Product... \n";

								if ( $cw_product->getReleaseDate() == '' ) {
									$post = array(
										'post_author' => '1',
										'post_status' => "publish",
										'post_name' => esc_attr($cw_product->getIdentifier()),
										'post_title' => esc_attr($cw_product->getName()),
										'post_type' => "product"
									);
								} else {
									$post = array(
										'post_author' => '1',
										'post_status' => "publish",
										'post_name' => esc_attr($cw_product->getIdentifier()),
										'post_title' => esc_attr($cw_product->getName()),
										'post_type' => "product",
										'post_date'     => $cw_product->getReleaseDate(),
										'post_date_gmt' => get_gmt_from_date( $cw_product->getReleaseDate() )
									);
								}

								//Create post
								$post_id = wp_insert_post( $post, $wp_error = false );
								
								//Add Additional Information
								wp_set_object_terms($post_id, 'simple', 'product_type');
								update_post_meta( $post_id, '_visibility', 'visible' );
								update_post_meta( $post_id, '_stock_status', 'outofstock');
								update_post_meta( $post_id, 'total_sales', '0');
								update_post_meta( $post_id, '_downloadable', 'no');
								update_post_meta( $post_id, '_virtual', 'yes');
								update_post_meta( $post_id, '_featured', "no" );
								update_post_meta( $post_id, '_sku', htmlspecialchars_decode($cw_product->getIdentifier()));
								update_post_meta( $post_id, '_product_attributes', array());
								update_post_meta( $post_id, '_manage_stock', "yes" );
								update_post_meta( $post_id, "_stock", esc_attr($cw_product->getStockQuantity())); 
								update_post_meta( $post_id, '_codeswholesale_product_id', $cw_product->getProductId() );
								update_post_meta( $post_id, '_codeswholesale_product_spread_type', "0" );
								update_post_meta( $post_id, '_codeswholesale_product_is_in_sale', "0" );

							}

							echo "Updateing Product... \n";

							$post_product = $products_ids[$cw_product->getProductId()];

							if ($post_product == null) {
								$post_product_id = $post_id;
							} else {
								$post_product_id = $post_product->ID;
							}
							
							$price = $cw_product->getDefaultPrice();

							echo $price."\n";
						
							$priceSpread = calculateSpread(getSpreadParams(), $price);

							echo $priceSpread."\n";
							
							$product_spread_type =  get_post_meta($post_product_id, "_codeswholesale_product_spread_type", true);
							
							$product_is_in_sale =  get_post_meta($post_product_id, "_codeswholesale_product_is_in_sale", true);
																					
							if ($cw_product->getStockQuantity() > 0) {
								update_post_meta($post_product_id, "_stock_status", "instock");
								update_post_meta($post_product_id, '_manage_stock', "yes" );
								update_post_meta($post_product_id, "_stock", esc_attr($cw_product->getStockQuantity())); 
							}
								
							if ($product_spread_type == 0) {
								if ($cw_product->getDefaultPrice() > 0) {
									if ($product_is_in_sale == 0) {
										update_post_meta($post_product_id, "_regular_price", esc_attr(round($priceSpread , 2)));
										update_post_meta($post_product_id, "_price", esc_attr(round($priceSpread , 2)));
									} else {
										update_post_meta($post_product_id, "_sale_price", esc_attr(round($priceSpread , 2)));
										update_post_meta($post_product_id, "_price", esc_attr(round($priceSpread , 2)));
									}
									update_post_meta($post_product_id, "_virtual", "yes");
								} else {
									update_post_meta($post_product_id, "_regular_price", esc_attr(0));
									update_post_meta($post_product_id, "_price", esc_attr(0));
								}
							}

							$i = 0;
							$category_id = array();

							$Platform = get_term_by('name', 'Platform', 'product_cat');
							$Region = get_term_by('name', 'Region', 'product_cat');
							$Language = get_term_by('name', 'Language', 'product_cat');

							$getRegions = $cw_product->getRegions();
							$getLanguages = $cw_product->getLanguages();
							$getPlatform = $cw_product->getPlatform();
							
							if ( empty( $Platform ) ) {
								wp_insert_term( 'Platform', 'product_cat', array( 'slug' => 'platform'));
							}

							$cwPlatform = get_term_by('slug', str_replace(' ', '-', $getPlatform), 'product_cat');
							if ( empty( $cwPlatform ) ) {
								wp_insert_term( $getPlatform, 'product_cat', array( 'slug' => $Platform, 'parent'=> term_exists( 'Platform', 'product_cat' )['term_id'] ));
								$cwPlatform = get_term_by('slug', str_replace(' ', '-', $getPlatform), 'product_cat');
							}
							echo $cwPlatform->name."\n";
							$category_id[$i] = $cwPlatform->term_id;
							$i++;	
							
							if ( empty( $Region ) ) {
								wp_insert_term( 'Region', 'product_cat', array( 'slug' => 'region'));
							}

							foreach ($getRegions as $getRegion) {
								$Regions  = get_term_by('slug', str_replace(' ', '-', $getRegion), 'product_cat');
								if ( empty( $Regions ) ) {
									wp_insert_term(  $getRegion, 'product_cat', array( 'slug' => $getRegion, 'parent'=> term_exists( 'Region', 'product_cat' )['term_id'] ));
									$Regions = get_term_by('slug', str_replace(' ', '-', $getRegion), 'product_cat');
								}
								echo $Regions->name."\n";
								$category_id[$i] = $Regions->term_id;
								$i++;
							}
							
							if ( empty( $Language ) ) {
								wp_insert_term( 'Language', 'product_cat', array( 'slug' => 'language'));
							}

							foreach ($getLanguages as $getLanguage) {
								$Languages = get_term_by('slug', str_replace(' ', '-', $getLanguage), 'product_cat');
								if ( empty( $Languages ) ) {
									wp_insert_term(  $getLanguage, 'product_cat', array( 'slug' => $getLanguage, 'parent'=> term_exists( 'Language', 'product_cat' )['term_id'] ));
									$Languages = get_term_by('slug', str_replace(' ', '-', $getLanguage), 'product_cat');
								}
								echo $Languages->name."\n";
								$category_id[$i] = $Languages->term_id;
								$i++;
							}

							$term_lists = wp_get_post_terms( $post_product_id, 'product_cat', array( 'fields' => 'ids' ) );
							$category_ids_ = array_merge($category_id, $term_lists);
							
							wp_set_post_terms($post_product_id, array_unique($category_ids_), "product_cat");
							update_post_meta($post_product_id, "product_cat", array_unique($category_ids_));
							
							update_post_meta($post_product_id, "post_title", htmlspecialchars(esc_attr($cw_product->getName())));

							// Returns Array of Term Names for "my_taxonomy".
							$term_list = wp_get_post_terms( $post_product_id, 'product_cat', array( 'fields' => 'names' ) );

							$cw_format_date = strtotime(str_replace( array('T',':00.000Z'), ' ', $cw_product->getReleaseDate() )); 
							update_post_meta( $post_product_id, "_wc_pre_orders_availability_datetime", $cw_format_date );
							$availability_timestamp_in_utc = (int) get_post_meta( $post_product_id, "_wc_pre_orders_availability_datetime", true );

							// if the availability date has passed
							if ( $availability_timestamp_in_utc > strtotime("now") ) {
								update_post_meta($post_product_id, "_wc_pre_orders_enabled", "yes");
								update_post_meta($post_product_id, "_wc_pre_orders_when_to_charge", "upfront");
								wp_update_post(
									array (
										'ID'            => $post_product_id, // ID of the post to update
										'post_status' 	=> "publish",
										'post_date'     => $cw_product->getReleaseDate(),
										'post_date_gmt' => get_gmt_from_date( $cw_product->getReleaseDate() )
									)
								);
								if ($cw_product->getStockQuantity() == 0) {
									update_post_meta($post_product_id, "_stock", esc_attr(0));
									update_post_meta( $post_product_id, "_stock_status", "onbackorder");
									update_post_meta( $post_product_id, "_backorders", "notify");								
								}
								if ($price == 0) {
									update_post_meta($post_product_id, "_wc_pre_orders_enabled", "no");
									update_post_meta($post_product_id, "_wc_pre_orders_when_to_charge", "");
									update_post_meta( $post_product_id, "_backorders", "no");	
									if ($cw_product->getStockQuantity() == 0) {
										update_post_meta($post_product_id, "_stock_status", "outofstock");
										update_post_meta($post_product_id, "_stock", esc_attr(0));
									}
								}
							} else {
								update_post_meta($post_product_id, "_wc_pre_orders_enabled", "no");
								update_post_meta($post_product_id, "_wc_pre_orders_when_to_charge", "");
								update_post_meta( $post_product_id, "_backorders", "no");	
								wp_update_post(
									array (
										'ID'            => $post_product_id, // ID of the post to update
										'post_status' 	=> "publish",
									)
								);
								if ($cw_product->getStockQuantity() == 0) {
									update_post_meta($post_product_id, "_stock_status", "outofstock");
									update_post_meta($post_product_id, "_stock", esc_attr(0));
								}
							}

							if ( $cw_product->getReleaseDate() != '' ) {
								wp_update_post(
									array (
										'ID'            => $post_product_id, // ID of the post to update
										'post_status' 	=> "publish",
										'post_date'     => $cw_product->getReleaseDate(),
										'post_date_gmt' => get_gmt_from_date( $cw_product->getReleaseDate() )
									)
								);
							}

							if ( !has_post_thumbnail($post_product_id) ) {
								$cw_img_url = $cw_product->getImageUrl(ImageType::MEDIUM);
								//update_post_meta( $post_product_id, "_cw_image_url", esc_attr($cw_img));
								
								if( !strpos($cw_img_url, "no-image") ) {													
									// Add Featured Image to Post
									$upload_dir = wp_upload_dir(); // Set upload folder
									$image_data = file_get_contents($cw_img_url); // Get image data
									$filename   = basename($cw_product->getProductId() . ".png"); // Create image file name

									// Check folder permission and define file location
									if( wp_mkdir_p( $upload_dir['path'] ) ) {
										$file = $upload_dir['path'] . '/' . $filename;
									} else {
										$file = $upload_dir['basedir'] . '/' . $filename;
									}

									// Create the image  file on the server
									file_put_contents( $file, $image_data );

									// Check image file type
									$wp_filetype = wp_check_filetype( $filename, null );

									// Set attachment data
									$attachment = array(
										'post_mime_type' => $wp_filetype['type'],
										'post_title'     => sanitize_file_name( $cw_product->getName() ),
										'post_content'   => '',
										'post_author'   => '1',
										'post_status'    => 'publish'
									);

									// Create the attachment
									$attach_id = wp_insert_attachment( $attachment, $file, $post_product_id );

									// Include image.php
									require_once(ABSPATH . 'wp-admin/includes/image.php');

									// Define attachment metadata
									$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

									// Assign metadata to attachment
									wp_update_attachment_metadata( $attach_id, $attach_data );

									// And finally assign featured image to post
									set_post_thumbnail( $post_product_id, $attach_id );
									
									echo $cw_img_url . "\n";
								}
							}	
							
							$getProductId = $cw_product->getProductId();
							update_post_meta($post_product_id, '_product_product_id', $getProductId);
							
							try{
								$product = array();
								$product = \CodesWholesale\Resource\ProductDescription::get($cw_product->getDescriptionHref());
								$preferredLanguage = CW()->get_options()[CodesWholesaleConst::PREFERRED_LANGUAGE_FOR_PRODUCT_OPTION_NAME];

								foreach ($product->getFactSheets() as $factSheet) {
									if (strtolower($preferredLanguage) === strtolower($factSheet->getTerritory())) {
										$description = $factSheet->getDescription();
										$data = array(
											'ID' => $post_product_id,
											'post_content' => $description,
										);						   
										wp_update_post( $data );
									}
								}

								$vv = array();
								$Categorys_id = array();
								
								foreach ($product->getCategories() as $Category) {
									wp_insert_term( $Category, 'product_cat', array( 'slug' => $Category));
									$Categorys = get_term_by('slug', str_replace(' ', '-', $Category), 'product_cat');
									$Categorys_id[] .= $Categorys->term_id;
								}

								$term_lists = wp_get_post_terms( $post_product_id, 'product_cat', array( 'fields' => 'ids' ) );
								$category_ids_ = array_merge($Categorys_id, $term_lists);
								wp_set_post_terms($post_product_id, array_unique($category_ids_), "product_cat");
								update_post_meta($post_product_id, "product_cat", array_unique($category_ids_));

								foreach ($product->getPhotos() as $photo) {
										// Add Featured Image to Post

										include_once( ABSPATH . 'wp-admin/includes/image.php' );
										$imageurl = $photo->getUrl();
										$imagetype = end(explode('/', getimagesize($imageurl)['mime']));
										$link_array = explode('/',$imageurl);
										$uniq_name = end($link_array);
										$filename = $uniq_name.'.png';

										global $wpdb;
										$does_file_exists = intval( $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value LIKE '%/$filename'" ) );
										
										if ( null == ( $thumb_id = $does_file_exists ) ) {
											$uploaddir = wp_upload_dir();
											$uploadfile = $uploaddir['path'] . '/' . $filename;
											$contents= file_get_contents($imageurl);
											$savefile = fopen($uploadfile, 'w');
											fwrite($savefile, $contents);
											fclose($savefile);
											
											$wp_filetype = wp_check_filetype(basename($filename), null );
											$attachment = array(
												'post_mime_type' => $wp_filetype['type'],
												'post_title'     => sanitize_file_name( $cw_product->getName() ),
												'post_content'   => '',
												'post_author'   => '1',
												'post_status'    => 'publish'
											);
											
											$attach_id = wp_insert_attachment( $attachment, $uploadfile );
											$imagenew = get_post( $attach_id );
											$fullsizepath = get_attached_file( $imagenew->ID );
											$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
											wp_update_attachment_metadata( $attach_id, $attach_data ); 
											
											echo $attach_id;

											$vv[] .= $attach_id;

										} else {
											$vv[] .= $thumb_id;
										}

										echo $photo->getUrl() . "\n";
								}

								print_r( $vv );
								$attach_id_implode = implode(', ', $vv);

								update_post_meta($post_product_id, '_product_image_gallery', $attach_id_implode);

								$getPegiRating = $product->getPegiRating();
								update_post_meta($post_product_id, '_product_pegi_rating', $getPegiRating);

								$getPlatform = $product->getPlatform();
								update_post_meta($post_product_id, '_product_platform', $getPlatform);

								$getReleases = $cw_product->getReleaseDate();
								update_post_meta($post_product_id, '_product_releases', $getReleases);

								$getEditions = $product->getEditions();
								update_post_meta($post_product_id, '_product_editions', $getEditions);

								$getDeveloperHomepage = $product->getDeveloperHomepage();
								update_post_meta($post_product_id, '_product_developer_homepage', $getDeveloperHomepage);

								$getKeywords = $product->getKeywords();
								wp_set_object_terms($post_product_id, $getKeywords, 'product_tag');
								update_post_meta($post_product_id, "product_tag", $getKeywords);

								$getGameLanguages = $product->getGameLanguages();
								update_post_meta($post_product_id, '_product_game_languages', $getGameLanguages);

								$getDeveloperName = $product->getDeveloperName();
								update_post_meta($post_product_id, '_product_developer_name', $getDeveloperName);

								$getEanCodes = $product->getEanCodes();
								update_post_meta($post_product_id, '_product_ean_codes', $getEanCodes);

								$getLastUpdate = $product->getLastUpdate(); 
								update_post_meta($post_product_id, '_product_last_update', $getLastUpdate);

								$getExtensionPacks = $product->getExtensionPacks();
								update_post_meta($post_product_id, '_product_extension_packs', $getExtensionPacks);

							} catch (Exception $e) {}

							// Update post 37
							$my_post = array(
								'ID'           => $post_product_id,
								'post_title'   => esc_attr($cw_product->getName())
							);

							// Update the post into the database
							wp_update_post( $my_post );
						}

					}

				$cw_products_no_mores = array_diff($cw_product_ids, $cw_products_names_array);

				if(count($cw_products_no_mores) != 0){
					$product_no_mores = get_posts(array(
						'post_type' => 'product',
						'post_status' => 'any',
						'meta_query' => array(
							array(
								'key' => CodesWholesaleConst::PRODUCT_CODESWHOLESALE_ID_PROP_NAME,
								'value' => $cw_products_no_mores,
								'compare' => 'IN'
							)
						),
						'numberposts' => -1
					));

					foreach($product_no_mores as $product_no_more){
						echo $product_no_more->ID;
						wp_trash_post($product_no_more->ID);
					}
				}

			echo "Stock updated. \n";

        }

add_action( 'init', function () {

	add_action( 'codeswholesale_update_stock_action', 'cron_job' );

	if (!wp_next_scheduled( 'codeswholesale_update_stock_action' ) ) {
		wp_schedule_single_event( strtotime('+2 minutes'), 'codeswholesale_update_stock_action' );
	}
});