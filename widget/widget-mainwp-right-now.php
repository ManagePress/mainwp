<?php

class MainWP_Right_Now {
	public static function getClassName() {
		return __CLASS__;
	}

	public static function init() {
		add_filter( 'plugins_api', array( 'MainWP_Right_Now', 'plugins_api' ), 10, 3 );
	}

	public static function plugins_api( $default, $action, $args ) {
		$url = $http_url = 'http://api.wordpress.org/plugins/info/1.0/';
		if ( $ssl = wp_http_supports( array( 'ssl' ) ) ) {
			$url = set_url_scheme( $url, 'https' );
		}

		$args    = array(
			'timeout' => 15,
			'body'    => array(
				'action'  => $action,
				'request' => serialize( $args ),
			),
		);
		$request = wp_remote_post( $url, $args );

		if ( is_wp_error( $request ) ) {
			$url  = '';
			$name = '';
			if ( isset( $_REQUEST['url'] ) ) {
				$url  = $_REQUEST['url'];
				$name = $_REQUEST['name'];
			}

			$res = new WP_Error( 'plugins_api_failed', __( '<h3>No Plugin Information Found.</h3> This may be a premium plugin and no other details are available from WordPress.', 'mainwp' ) . ' ' . ( $url == '' ? __( 'Please visit the Plugin website for more information.', 'mainwp' ) : __( 'Please visit the Plugin website for more information: ', 'mainwp' ) . '<a href="' . rawurldecode( $url ) . '" target="_blank">' . rawurldecode( $name ) . '</a>' ), $request->get_error_message() );

			return $res;
		}

		return $default;
	}

	public static function getName() {
		return '<i class="fa fa-pie-chart"></i> ' . __( 'Right Now', 'mainwp' );
	}

	public static function render() {
		?>
		<div id="rightnow_list" xmlns="http://www.w3.org/1999/html"><?php MainWP_Right_Now::renderSites(); ?></div>
		<?php
	}

	public static function upgradeSite( $id ) {
		if ( isset( $id ) && MainWP_Utility::ctype_digit( $id ) ) {
			$website = MainWP_DB::Instance()->getWebsiteById( $id );
			if ( MainWP_Utility::can_edit_website( $website ) ) {
				$information = MainWP_Utility::fetchUrlAuthed( $website, 'upgrade' );

				if ( isset( $information['upgrade'] ) && ( $information['upgrade'] == 'SUCCESS' ) ) {
					MainWP_DB::Instance()->updateWebsiteOption( $website, 'wp_upgrades', json_encode( array() ) );

					return __( 'Update Successful', 'mainwp' ) . '! Open your site <a href="' . esc_url( $website->url ) . '" target="_blank">Front page</a> or <a href="admin.php?page=SiteOpen&newWindow=yes&websiteid=' . $id . '" target="_blank">WP Admin</a>';
				} else if ( isset( $information['upgrade'] ) ) {
					$errorMsg = '';
					if ( $information['upgrade'] == 'LOCALIZATION' ) {
						$errorMsg = __( 'No update found for your set locale', 'mainwp' );
					} else if ( $information['upgrade'] == 'NORESPONSE' ) {
						$errorMsg = __( 'No response from the WordPress update server', 'mainwp' );
					}

					throw new MainWP_Exception( 'WPERROR', $errorMsg );
				} else if ( isset( $information['error'] ) ) {
					throw new MainWP_Exception( 'WPERROR', $information['error'] );
				} else {
					throw new MainWP_Exception( 'ERROR', __( 'Invalid response from site', 'mainwp' ) );
				}
			}
		}

		throw new MainWP_Exception( 'ERROR', __( 'Invalid Request', 'mainwp' ) );
	}

	public static function ignorePluginTheme( $type, $slug, $name, $id ) {
		if ( isset( $id ) && MainWP_Utility::ctype_digit( $id ) ) {
			$website = MainWP_DB::Instance()->getWebsiteById( $id );
			if ( MainWP_Utility::can_edit_website( $website ) ) {
				$slug = urldecode( $slug );
				if ( $type == 'plugin' ) {
					$decodedIgnoredPlugins = json_decode( $website->ignored_plugins, true );
					if ( ! isset( $decodedIgnoredPlugins[ $slug ] ) ) {
						$decodedIgnoredPlugins[ $slug ] = urldecode( $name );
						MainWP_DB::Instance()->updateWebsiteValues( $website->id, array( 'ignored_plugins' => json_encode( $decodedIgnoredPlugins ) ) );
					}
				} else if ( $type == 'theme' ) {
					$decodedIgnoredThemes = json_decode( $website->ignored_themes, true );
					if ( ! isset( $decodedIgnoredThemes[ $slug ] ) ) {
						$decodedIgnoredThemes[ $slug ] = urldecode( $name );
						MainWP_DB::Instance()->updateWebsiteValues( $website->id, array( 'ignored_themes' => json_encode( $decodedIgnoredThemes ) ) );
					}
				}
			}
		}

		return 'success';
	}

	public static function unIgnorePluginTheme( $type, $slug, $id ) {
		if ( isset( $id ) ) {
			if ( $id == '_ALL_' ) {
				$websites = MainWP_DB::Instance()->query( MainWP_DB::Instance()->getSQLWebsitesForCurrentUser() );
				while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
					if ( $type == 'plugin' ) {
						MainWP_DB::Instance()->updateWebsiteValues( $website->id, array( 'ignored_plugins' => json_encode( array() ) ) );
					} else if ( $type == 'theme' ) {
						MainWP_DB::Instance()->updateWebsiteValues( $website->id, array( 'ignored_themes' => json_encode( array() ) ) );
					}
				}
				@MainWP_DB::free_result( $websites );
			} else if ( MainWP_Utility::ctype_digit( $id ) ) {
				$website = MainWP_DB::Instance()->getWebsiteById( $id );
				if ( MainWP_Utility::can_edit_website( $website ) ) {
					$slug = urldecode( $slug );
					if ( $type == 'plugin' ) {
						$decodedIgnoredPlugins = json_decode( $website->ignored_plugins, true );
						if ( isset( $decodedIgnoredPlugins[ $slug ] ) ) {
							unset( $decodedIgnoredPlugins[ $slug ] );
							MainWP_DB::Instance()->updateWebsiteValues( $website->id, array( 'ignored_plugins' => json_encode( $decodedIgnoredPlugins ) ) );
						}
					} else if ( $type == 'theme' ) {
						$decodedIgnoredThemes = json_decode( $website->ignored_themes, true );
						if ( isset( $decodedIgnoredThemes[ $slug ] ) ) {
							unset( $decodedIgnoredThemes[ $slug ] );
							MainWP_DB::Instance()->updateWebsiteValues( $website->id, array( 'ignored_themes' => json_encode( $decodedIgnoredThemes ) ) );
						}
					}
				}
			}
		}

		return 'success';
	}

	public static function ignorePluginsThemes( $type, $slug, $name ) {
		$slug          = urldecode( $slug );
		$userExtension = MainWP_DB::Instance()->getUserExtension();
		if ( $type == 'plugin' ) {
			$decodedIgnoredPlugins = json_decode( $userExtension->ignored_plugins, true );
			if ( ! is_array( $decodedIgnoredPlugins ) ) {
				$decodedIgnoredPlugins = array();
			}
			$decodedIgnoredPlugins[ $slug ] = urldecode( $name );
			MainWP_DB::Instance()->updateUserExtension( array(
				'userid'          => null,
				'ignored_plugins' => json_encode( $decodedIgnoredPlugins ),
			) );
		} else if ( $type == 'theme' ) {
			$decodedIgnoredThemes = json_decode( $userExtension->ignored_themes, true );
			if ( ! is_array( $decodedIgnoredThemes ) ) {
				$decodedIgnoredThemes = array();
			}
			$decodedIgnoredThemes[ $slug ] = urldecode( $name );
			MainWP_DB::Instance()->updateUserExtension( array(
				'userid'         => null,
				'ignored_themes' => json_encode( $decodedIgnoredThemes ),
			) );
		}

		return 'success';
	}

	public static function unIgnorePluginsThemes( $type, $slug ) {
		$slug          = urldecode( $slug );
		$userExtension = MainWP_DB::Instance()->getUserExtension();
		if ( $type == 'plugin' ) {
			if ( $slug == '_ALL_' ) {
				$decodedIgnoredPlugins = array();
			} else {
				$decodedIgnoredPlugins = json_decode( $userExtension->ignored_plugins, true );
				if ( ! is_array( $decodedIgnoredPlugins ) ) {
					$decodedIgnoredPlugins = array();
				}
				if ( isset( $decodedIgnoredPlugins[ $slug ] ) ) {
					unset( $decodedIgnoredPlugins[ $slug ] );
				}
			}
			MainWP_DB::Instance()->updateUserExtension( array(
				'userid'          => null,
				'ignored_plugins' => json_encode( $decodedIgnoredPlugins ),
			) );
		} else if ( $type == 'theme' ) {
			if ( $slug == '_ALL_' ) {
				$decodedIgnoredThemes = array();
			} else {
				$decodedIgnoredThemes = json_decode( $userExtension->ignored_plugins, true );
				if ( ! is_array( $decodedIgnoredThemes ) ) {
					$decodedIgnoredThemes = array();
				}
				if ( isset( $decodedIgnoredThemes[ $slug ] ) ) {
					unset( $decodedIgnoredThemes[ $slug ] );
				}
			}
			MainWP_DB::Instance()->updateUserExtension( array(
				'userid'         => null,
				'ignored_themes' => json_encode( $decodedIgnoredThemes ),
			) );
		}

		return 'success';
	}

	public static function unIgnoreAbandonedPluginTheme( $type, $slug, $id ) {
		if ( isset( $id ) ) {
			if ( $id == '_ALL_' ) {
				$websites = MainWP_DB::Instance()->query( MainWP_DB::Instance()->getSQLWebsitesForCurrentUser() );
				while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
					if ( $type == 'plugin' ) {
						MainWP_DB::Instance()->updateWebsiteOption( $website, 'plugins_outdate_dismissed', @json_encode( array() ) );
					} else if ( $type == 'theme' ) {
						MainWP_DB::Instance()->updateWebsiteOption( $website, 'themes_outdate_dismissed', @json_encode( array() ) );
					}
				}
				@MainWP_DB::free_result( $websites );
			} else if ( MainWP_Utility::ctype_digit( $id ) ) {
				$website = MainWP_DB::Instance()->getWebsiteById( $id );
				if ( MainWP_Utility::can_edit_website( $website ) ) {
					$slug = urldecode( $slug );
					if ( $type == 'plugin' ) {
						$decodedIgnoredPlugins = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'plugins_outdate_dismissed' ), true );
						if ( isset( $decodedIgnoredPlugins[ $slug ] ) ) {
							unset( $decodedIgnoredPlugins[ $slug ] );
							MainWP_DB::Instance()->updateWebsiteOption( $website, 'plugins_outdate_dismissed', @json_encode( $decodedIgnoredPlugins ) );
						}
					} else if ( $type == 'theme' ) {
						$decodedIgnoredThemes = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'themes_outdate_dismissed' ), true );
						if ( isset( $decodedIgnoredThemes[ $slug ] ) ) {
							unset( $decodedIgnoredThemes[ $slug ] );
							MainWP_DB::Instance()->updateWebsiteOption( $website, 'themes_outdate_dismissed', @json_encode( $decodedIgnoredThemes ) );
						}
					}
				}
			}
		}

		return 'success';
	}

	public static function unIgnoreAbandonedPluginsThemes( $type, $slug ) {
		$slug          = urldecode( $slug );
		$userExtension = MainWP_DB::Instance()->getUserExtension();
		if ( $type == 'plugin' ) {
			if ( $slug == '_ALL_' ) {
				$decodedIgnoredPlugins = array();
			} else {
				$decodedIgnoredPlugins = json_decode( $userExtension->dismissed_plugins, true );
				if ( ! is_array( $decodedIgnoredPlugins ) ) {
					$decodedIgnoredPlugins = array();
				}
				if ( isset( $decodedIgnoredPlugins[ $slug ] ) ) {
					unset( $decodedIgnoredPlugins[ $slug ] );
				}
			}
			MainWP_DB::Instance()->updateUserExtension( array(
				'userid'            => null,
				'dismissed_plugins' => json_encode( $decodedIgnoredPlugins ),
			) );
		} else if ( $type == 'theme' ) {
			if ( $slug == '_ALL_' ) {
				$decodedIgnoredThemes = array();
			} else {
				$decodedIgnoredThemes = json_decode( $userExtension->dismissed_themes, true );
				if ( ! is_array( $decodedIgnoredThemes ) ) {
					$decodedIgnoredThemes = array();
				}
				if ( isset( $decodedIgnoredThemes[ $slug ] ) ) {
					unset( $decodedIgnoredThemes[ $slug ] );
				}
			}
			MainWP_DB::Instance()->updateUserExtension( array(
				'userid'           => null,
				'dismissed_themes' => json_encode( $decodedIgnoredThemes ),
			) );
		}

		return 'success';
	}

	public static function dismissPluginTheme( $type, $slug, $name, $id ) {
		if ( isset( $id ) && MainWP_Utility::ctype_digit( $id ) ) {
			$website = MainWP_DB::Instance()->getWebsiteById( $id );
			if ( MainWP_Utility::can_edit_website( $website ) ) {
				$slug = urldecode( $slug );
				if ( $type == 'plugin' ) {
					$decodedDismissedPlugins = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'plugins_outdate_dismissed' ), true );
					if ( ! isset( $decodedDismissedPlugins[ $slug ] ) ) {
						$decodedDismissedPlugins[ $slug ] = urldecode( $name );
						MainWP_DB::Instance()->updateWebsiteOption( $website, 'plugins_outdate_dismissed', @json_encode( $decodedDismissedPlugins ) );
					}
				} else if ( $type == 'theme' ) {
					$decodedDismissedThemes = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'themes_outdate_dismissed' ), true );
					if ( ! isset( $decodedDismissedThemes[ $slug ] ) ) {
						$decodedDismissedThemes[ $slug ] = urldecode( $name );
						MainWP_DB::Instance()->updateWebsiteOption( $website, 'themes_outdate_dismissed', @json_encode( $decodedDismissedThemes ) );
					}
				}
			}
		}

		return 'success';
	}

	public static function dismissPluginsThemes( $type, $slug, $name ) {
		$slug          = urldecode( $slug );
		$userExtension = MainWP_DB::Instance()->getUserExtension();
		if ( $type == 'plugin' ) {
			$decodedDismissedPlugins = json_decode( $userExtension->dismissed_plugins, true );
			if ( ! is_array( $decodedDismissedPlugins ) ) {
				$decodedDismissedPlugins = array();
			}
			$decodedDismissedPlugins[ $slug ] = urldecode( $name );
			MainWP_DB::Instance()->updateUserExtension( array(
				'userid'            => null,
				'dismissed_plugins' => json_encode( $decodedDismissedPlugins ),
			) );
		} else if ( $type == 'theme' ) {
			$decodedDismissedThemes = json_decode( $userExtension->dismissed_themes, true );
			if ( ! is_array( $decodedDismissedThemes ) ) {
				$decodedDismissedThemes = array();
			}
			$decodedDismissedThemes[ $slug ] = urldecode( $name );
			MainWP_DB::Instance()->updateUserExtension( array(
				'userid'           => null,
				'dismissed_themes' => json_encode( $decodedDismissedThemes ),
			) );
		}

		return 'success';
	}

	/*
     * $id = site id in db
     * $type = theme/plugin
     * $list = name of theme/plugin (seperated by ,)
     */
	public static function upgradePluginThemeTranslation( $id, $type, $list ) {
		if ( isset( $id ) && MainWP_Utility::ctype_digit( $id ) ) {
			$website = MainWP_DB::Instance()->getWebsiteById( $id );
			if ( MainWP_Utility::can_edit_website( $website ) ) {
				$information = MainWP_Utility::fetchUrlAuthed( $website, ( 'translation' === $type ? 'upgradetranslation' : 'upgradeplugintheme' ), array(
					'type' => $type,
					'list' => urldecode( $list ),
				), true );
				if ( isset( $information['upgrades'] ) ) {
					$tmp = array();
					if ( isset( $information['upgrades'] ) ) {
						foreach ( $information['upgrades'] as $k => $v ) {
							$tmp[ urlencode( $k ) ] = $v;
						}
					}
					return $tmp;
				} else if ( isset( $information['error'] ) ) {
					throw new MainWP_Exception( 'WPERROR', $information['error'] );
				} else {
					throw new MainWP_Exception( 'ERROR', 'Invalid response from site' );
				}
			}
		}
		throw new MainWP_Exception( 'ERROR', __( 'Invalid request', 'mainwp' ) );
	}

	/*
     * $id = site id in db
     * $type = theme/plugin
     */
	//todo: rename for Translation
	public static function getPluginThemeSlugs( $id, $type ) {

		$userExtension = MainWP_DB::Instance()->getUserExtension();
		$sql           = MainWP_DB::Instance()->getSQLWebsiteById( $id );
		$websites      = MainWP_DB::Instance()->query( $sql );
		$website       = @MainWP_DB::fetch_object( $websites );

		$slugs = array();
		if ( $type == 'plugin' ) {
			if ( $website->is_ignorePluginUpdates ) {
				return '';
			}

			$plugin_upgrades        = json_decode( $website->plugin_upgrades, true );
			$decodedPremiumUpgrades = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'premium_upgrades' ), true );
			if ( is_array( $decodedPremiumUpgrades ) ) {
				foreach ( $decodedPremiumUpgrades as $crrSlug => $premiumUpgrade ) {
					$premiumUpgrade['premium'] = true;

					if ( $premiumUpgrade['type'] == 'plugin' ) {
						if ( ! is_array( $plugin_upgrades ) ) {
							$plugin_upgrades = array();
						}
						$plugin_upgrades[ $crrSlug ] = $premiumUpgrade;
					}
				}
			}

			$ignored_plugins = json_decode( $website->ignored_plugins, true );
			if ( is_array( $ignored_plugins ) ) {
				$plugin_upgrades = array_diff_key( $plugin_upgrades, $ignored_plugins );
			}

			$ignored_plugins = json_decode( $userExtension->ignored_plugins, true );
			if ( is_array( $ignored_plugins ) ) {
				$plugin_upgrades = array_diff_key( $plugin_upgrades, $ignored_plugins );
			}

			if ( is_array( $plugin_upgrades ) ) {
				foreach ( $plugin_upgrades as $plugin_name => $plugin_upgrade ) {
					$slugs[] = urlencode( $plugin_name );
				}
			}
		} else if ( $type == 'theme' ) {

			if ( $website->is_ignoreThemeUpdates ) {
				return '';
			}

			$theme_upgrades         = json_decode( $website->theme_upgrades, true );
			$decodedPremiumUpgrades = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'premium_upgrades' ), true );
			if ( is_array( $decodedPremiumUpgrades ) ) {
				foreach ( $decodedPremiumUpgrades as $crrSlug => $premiumUpgrade ) {
					$premiumUpgrade['premium'] = true;

					if ( $premiumUpgrade['type'] == 'theme' ) {
						if ( ! is_array( $theme_upgrades ) ) {
							$theme_upgrades = array();
						}
						$theme_upgrades[ $crrSlug ] = $premiumUpgrade;
					}
				}
			}

			$ignored_themes = json_decode( $website->ignored_themes, true );
			if ( is_array( $ignored_themes ) ) {
				$theme_upgrades = array_diff_key( $theme_upgrades, $ignored_themes );
			}

			$ignored_themes = json_decode( $userExtension->ignored_themes, true );
			if ( is_array( $ignored_themes ) ) {
				$theme_upgrades = array_diff_key( $theme_upgrades, $ignored_themes );
			}

			if ( is_array( $theme_upgrades ) ) {
				foreach ( $theme_upgrades as $slug => $theme_upgrade ) {
					$slugs[] = $slug;
				}
			}
		} else if ( $type == 'translation' ) {
			$translation_upgrades         = json_decode( $website->translation_upgrades, true );
			if ( is_array( $translation_upgrades ) ) {
				foreach ( $translation_upgrades as $translation_upgrade ) {
					$slugs[] = $translation_upgrade['slug'];
				}
			}
		}

		return implode( ',', $slugs );
	}

	public static function renderLastUpdate() {
		$currentwp = MainWP_Utility::get_current_wpid();
		if ( ! empty( $currentwp ) ) {
			$website = MainWP_DB::Instance()->getWebsiteById( $currentwp );
			$dtsSync = $website->dtsSync;
		} else {
			$dtsSync = MainWP_DB::Instance()->getFirstSyncedSite();
		}

		if ( $dtsSync == 0 ) {
			//No settings saved!
			return;
		} else {
			echo __( '(Last complete sync: ', 'mainwp' ) . MainWP_Utility::formatTimestamp( MainWP_Utility::getTimestamp( $dtsSync ) ) . ')';
		}
	}

	public static function syncSite() {
		$website = null;
		if ( isset( $_POST['wp_id'] ) ) {
			$website = MainWP_DB::Instance()->getWebsiteById( $_POST['wp_id'] );
		}

		if ( $website == null ) {
			die( json_encode( array( 'error' => 'Invalid Request' ) ) );
		}

		$maxRequestsInThirtySeconds = get_option( 'mainwp_maximumRequests' );
		MainWP_Utility::endSession();

		$semLock = '103218'; //SNSyncLock
		//        $identifier = null;
		//        if ($maxRequestsInThirtySeconds != false || $maxRequestsInThirtySeconds != 0)
		//        {
		//            //Lock
		//            $identifier = MainWP_Utility::getLockIdentifier($semLock);
		//            MainWP_Utility::lock($identifier);
		//
		//            $req = MainWP_DB::Instance()->getRequestsSince(30 / $maxRequestsInThirtySeconds);
		//            MainWP_Utility::endSession();
		//
		//            while ($req >= 1)
		//            {
		//                MainWP_Utility::release($identifier);
		//                //Unlock
		//                sleep(2);
		//
		//                //Lock
		//                MainWP_Utility::lock($identifier);
		//                $req = MainWP_DB::Instance()->getRequestsSince(30 / $maxRequestsInThirtySeconds);
		//                MainWP_Utility::endSession();
		//            }
		//        }

		MainWP_DB::Instance()->updateWebsiteSyncValues( $website->id, array( 'dtsSyncStart' => time() ) );
		MainWP_Utility::endSession();

		//Unlock
		//        MainWP_Utility::release($identifier);
		if ( MainWP_Sync::syncSite( $website ) ) {
			die( json_encode( array( 'result' => 'SUCCESS' ) ) );
		}

		$website = MainWP_DB::Instance()->getWebsiteById( $website->id );

		die( json_encode( array( 'error' => $website->sync_errors ) ) );
	}

	public static function renderSites( $isUpdatesPage = false ) {
		$globalView = true;

		$current_wpid = MainWP_Utility::get_current_wpid();

		if ( $current_wpid ) {
			$sql        = MainWP_DB::Instance()->getSQLWebsiteById( $current_wpid, false, array( 'premium_upgrades', 'plugins_outdate_dismissed', 'themes_outdate_dismissed', 'plugins_outdate_info', 'themes_outdate_info', 'favi_icon' ) );
			$globalView = false;
		} else {
			$sql = MainWP_DB::Instance()->getSQLWebsitesForCurrentUser(false, null, 'wp.url', false, false, null, false, array( 'premium_upgrades', 'plugins_outdate_dismissed', 'themes_outdate_dismissed', 'plugins_outdate_info', 'themes_outdate_info', 'favi_icon' ) );
		}

		$websites = MainWP_DB::Instance()->query( $sql );

		if ( ! $websites ) {
			return;
		}

		$userExtension = MainWP_DB::Instance()->getUserExtension();

		$total_themesIgnored          = $total_pluginsIgnored = 0;
		$total_themesIgnoredAbandoned = $total_pluginsIgnoredAbandoned = 0;

		if ( $globalView ) {
			$decodedIgnoredPlugins = json_decode( $userExtension->ignored_plugins, true );
			$decodedIgnoredThemes  = json_decode( $userExtension->ignored_themes, true );
			$total_pluginsIgnored  = is_array( $decodedIgnoredPlugins ) ? count( $decodedIgnoredPlugins ) : 0;
			$total_themesIgnored   = is_array( $decodedIgnoredThemes ) ? count( $decodedIgnoredThemes ) : 0;

			$decodedIgnoredPluginsAbandoned = json_decode( $userExtension->dismissed_plugins, true );
			$decodedIgnoredThemesAbandoned  = json_decode( $userExtension->dismissed_themes, true );
			$total_pluginsIgnoredAbandoned  = is_array( $decodedIgnoredPluginsAbandoned ) ? count( $decodedIgnoredPluginsAbandoned ) : 0;
			$total_themesIgnoredAbandoned   = is_array( $decodedIgnoredThemesAbandoned ) ? count( $decodedIgnoredThemesAbandoned ) : 0;
		}

		$decodedDismissedPlugins = json_decode( $userExtension->dismissed_plugins, true );
		$decodedDismissedThemes  = json_decode( $userExtension->dismissed_themes, true );

		$total_wp_upgrades     = 0;
		$total_plugin_upgrades = 0;
		$total_translation_upgrades = 0;
		$total_theme_upgrades  = 0;
		$total_sync_errors     = 0;
		$total_uptodate        = 0;
		$total_offline         = 0;
		$total_plugins_outdate = 0;
		$total_themes_outdate  = 0;

		$allTranslations  = array();
		$translationsInfo = array();
		$allPlugins  = array();
		$pluginsInfo = array();
		$allThemes   = array();
		$themesInfo  = array();

		$allPluginsOutdate  = array();
		$pluginsOutdateInfo = array();

		$allThemesOutdate  = array();
		$themesOutdateInfo = array();

		@MainWP_DB::data_seek( $websites, 0 );

		$currentSite = null;

		$pluginsIgnored_perSites          = $themesIgnored_perSites = array();
		$pluginsIgnoredAbandoned_perSites = $themesIgnoredAbandoned_perSites = array();

		while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
			if ( ! $globalView ) {
				$currentSite = $website;
			}

			$wp_upgrades = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'wp_upgrades' ), true );
			if ( $website->is_ignoreCoreUpdates ) {
				$wp_upgrades = array();
			}

			if ( is_array( $wp_upgrades ) && count( $wp_upgrades ) > 0 ) {
				$total_wp_upgrades ++;
			}

			$translation_upgrades = json_decode( $website->translation_upgrades, true );
//			if ( $website->is_ignoreTranlsationUpdates ) {
//				$translation_upgrades = array();
//			}

			$plugin_upgrades = json_decode( $website->plugin_upgrades, true );
			if ( $website->is_ignorePluginUpdates ) {
				$plugin_upgrades = array();
			}

			$theme_upgrades = json_decode( $website->theme_upgrades, true );
			if ( $website->is_ignoreThemeUpdates ) {
				$theme_upgrades = array();
			}

			$decodedPremiumUpgrades = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'premium_upgrades' ), true );
			if ( is_array( $decodedPremiumUpgrades ) ) {
				foreach ( $decodedPremiumUpgrades as $crrSlug => $premiumUpgrade ) {
					$premiumUpgrade['premium'] = true;

					if ( $premiumUpgrade['type'] == 'plugin' ) {
						if ( ! is_array( $plugin_upgrades ) ) {
							$plugin_upgrades = array();
						}
						if ( ! $website->is_ignorePluginUpdates ) {
							$plugin_upgrades[ $crrSlug ] = $premiumUpgrade;
						}
					} else if ( $premiumUpgrade['type'] == 'theme' ) {
						if ( ! is_array( $theme_upgrades ) ) {
							$theme_upgrades = array();
						}
						if ( ! $website->is_ignoreThemeUpdates ) {
							$theme_upgrades[ $crrSlug ] = $premiumUpgrade;
						}
					}
				}
			}

			if ( is_array( $translation_upgrades ) ) {
//				$ignored_translations = json_decode( $website->ignored_translations, true );
//				if ( is_array( $ignored_translations ) ) {
//					$translation_upgrades = array_diff_key( $translation_upgrades, $ignored_translations );
//				}
//
//				$ignored_translations = json_decode( $userExtension->ignored_translations, true );
//				if ( is_array( $ignored_translations ) ) {
//					$translation_upgrades = array_diff_key( $translation_upgrades, $ignored_translations );
//				}

				$total_translation_upgrades += count( $translation_upgrades );
			}

			if ( is_array( $plugin_upgrades ) ) {
				$ignored_plugins = json_decode( $website->ignored_plugins, true );
				if ( is_array( $ignored_plugins ) ) {
					$plugin_upgrades = array_diff_key( $plugin_upgrades, $ignored_plugins );
				}

				$ignored_plugins = json_decode( $userExtension->ignored_plugins, true );
				if ( is_array( $ignored_plugins ) ) {
					$plugin_upgrades = array_diff_key( $plugin_upgrades, $ignored_plugins );
				}

				$total_plugin_upgrades += count( $plugin_upgrades );
			}

			if ( is_array( $theme_upgrades ) ) {
				$ignored_themes = json_decode( $website->ignored_themes, true );
				if ( is_array( $ignored_themes ) ) {
					$theme_upgrades = array_diff_key( $theme_upgrades, $ignored_themes );
				}

				$ignored_themes = json_decode( $userExtension->ignored_themes, true );
				if ( is_array( $ignored_themes ) ) {
					$theme_upgrades = array_diff_key( $theme_upgrades, $ignored_themes );
				}

				$total_theme_upgrades += count( $theme_upgrades );
			}

			$ignored_plugins = json_decode( $website->ignored_plugins, true );
			$ignored_themes  = json_decode( $website->ignored_themes, true );
			if ( is_array( $ignored_plugins ) ) {
				$ignored_plugins         = array_filter( $ignored_plugins );
				$pluginsIgnored_perSites = array_merge( $pluginsIgnored_perSites, $ignored_plugins );
			}
			if ( is_array( $ignored_themes ) ) {
				$ignored_themes         = array_filter( $ignored_themes );
				$themesIgnored_perSites = array_merge( $themesIgnored_perSites, $ignored_themes );
			}

			$ignoredAbandoned_plugins = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'plugins_outdate_dismissed' ), true );
			if ( is_array( $ignoredAbandoned_plugins ) ) {
				$ignoredAbandoned_plugins         = array_filter( $ignoredAbandoned_plugins );
				$pluginsIgnoredAbandoned_perSites = array_merge( $pluginsIgnoredAbandoned_perSites, $ignoredAbandoned_plugins );
			}
			$ignoredAbandoned_themes = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'themes_outdate_dismissed' ), true );
			if ( is_array( $ignoredAbandoned_themes ) ) {
				$ignoredAbandoned_themes         = array_filter( $ignoredAbandoned_themes );
				$themesIgnoredAbandoned_perSites = array_merge( $themesIgnoredAbandoned_perSites, $ignoredAbandoned_themes );
			}

			$plugins_outdate = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'plugins_outdate_info' ), true );
			$themes_outdate  = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'themes_outdate_info' ), true );

			if ( is_array( $plugins_outdate ) ) {
				if ( is_array( $ignoredAbandoned_plugins ) ) {
					$plugins_outdate = array_diff_key( $plugins_outdate, $ignoredAbandoned_plugins );
				}

				if ( is_array( $decodedDismissedPlugins ) ) {
					$plugins_outdate = array_diff_key( $plugins_outdate, $decodedDismissedPlugins );
				}

				$total_plugins_outdate += count( $plugins_outdate );
			}

			if ( is_array( $themes_outdate ) ) {
				if ( is_array( $themesIgnoredAbandoned_perSites ) ) {
					$themes_outdate = array_diff_key( $themes_outdate, $themesIgnoredAbandoned_perSites );
				}

				if ( is_array( $decodedDismissedThemes ) ) {
					$themes_outdate = array_diff_key( $themes_outdate, $decodedDismissedThemes );
				}

				$total_themes_outdate += count( $themes_outdate );
			}

			if ( $userExtension->site_view == 0 ) { //site view disabled
				if ( is_array( $translation_upgrades ) ) {
					foreach ( $translation_upgrades as $translation_upgrade ) {
						$slug = $translation_upgrade['slug'];
						if ( ! isset( $allTranslations[ $slug ] ) ) {
							$allTranslations[ $slug ] = array('name' => isset( $translation_upgrade['name'] ) ? $translation_upgrade['name'] : $slug, 'cnt' => 1);
						} else {
							$allTranslations[ $slug ]['cnt'] ++;
						}

						$translationsInfo[ $slug ] = array(
							'name'    => isset( $translation_upgrade['name'] ) ? $translation_upgrade['name'] : $slug,
							'slug'    => $slug,
							'version' => $translation_upgrade['version']
						);
					}
				}
				//ksort( $allTranslations );
				MainWP_Utility::array_sort( $allTranslations, 'name' );

				//Keep track of all the plugins & themes
				if ( is_array( $plugin_upgrades ) ) {
					foreach ( $plugin_upgrades as $slug => $plugin_upgrade ) {
						if ( ! isset( $allPlugins[ $slug ] ) ) {
							$allPlugins[ $slug ] = array('name' => $plugin_upgrade['Name'], 'cnt' => 1);
						} else {
							$allPlugins[ $slug ]['cnt'] ++;
						}

						$pluginsInfo[ $slug ] = array(
							'name'    => $plugin_upgrade['Name'],
							'slug'    => $plugin_upgrade['update']['slug'],
							'premium' => ( isset( $plugin_upgrade['premium'] ) ? $plugin_upgrade['premium'] : 0 ),
							'uri'     => $plugin_upgrade['PluginURI'],
						);
					}
				}
				//ksort( $allPlugins );
				MainWP_Utility::array_sort( $allPlugins, 'name' );



				if ( is_array( $theme_upgrades ) ) {
					foreach ( $theme_upgrades as $slug => $theme_upgrade ) {
						if ( ! isset( $allThemes[ $slug ] ) ) {
							$allThemes[ $slug ] = array('name' => $theme_upgrade['Name'], 'cnt' => 1);
						} else {
							$allThemes[ $slug ]['cnt'] ++;
						}

						$themesInfo[ $slug ] = array(
							'name'    => $theme_upgrade['Name'],
							'premium' => ( isset( $theme_upgrade['premium'] ) ? $theme_upgrade['premium'] : 0 ),
						);
					}
				}
				//ksort( $allThemes );
				MainWP_Utility::array_sort( $allThemes, 'name' );

				if ( is_array( $plugins_outdate ) ) {
					foreach ( $plugins_outdate as $slug => $plugin_outdate ) {
						if ( ! isset( $allPluginsOutdate[ $slug ] ) ) {
							$allPluginsOutdate[ $slug ] = array( 'name' => $plugin_outdate['Name'], 'cnt' => 1);
						} else {
							$allPluginsOutdate[ $slug ]['cnt'] ++;
						}
						$pluginsOutdateInfo[ $slug ] = array(
							'Name'         => $plugin_outdate['Name'],
							'last_updated' => ( isset( $plugin_outdate['last_updated'] ) ? $plugin_outdate['last_updated'] : 0 ),
							'info'         => $plugin_outdate,
							'uri'          => $plugin_outdate['PluginURI'],
						);
					}
				}
				//ksort( $allPluginsOutdate );
				MainWP_Utility::array_sort( $allPluginsOutdate, 'name' );

				if ( is_array( $themes_outdate ) ) {
					foreach ( $themes_outdate as $slug => $theme_outdate ) {
						if ( ! isset( $allThemesOutdate[ $slug ] ) ) {
							$allThemesOutdate[ $slug ] = array('name' => $theme_outdate['Name'], 'cnt' => 1 );
						} else {
							$allThemesOutdate[ $slug ]['cnt'] ++;
						}
						$themesOutdateInfo[ $slug ] = array(
							'name'         => $theme_outdate['Name'],
							'slug'         => dirname( $slug ),
							'last_updated' => ( isset( $theme_outdate['last_updated'] ) ? $theme_outdate['last_updated'] : 0 ),
						);
					}
				}
				//ksort( $allThemesOutdate );
				MainWP_Utility::array_sort( $allThemesOutdate, 'name' );

			}

			if ( $website->sync_errors != '' ) {
				$total_sync_errors ++;
			}
			if ( $website->uptodate == 1 ) {
				$total_uptodate ++;
			}
			if ( $website->offline_check_result == - 1 ) {
				$total_offline ++;
			}

		}

		$total_pluginsIgnored += count( $pluginsIgnored_perSites );
		$total_themesIgnored += count( $themesIgnored_perSites );

		$total_pluginsIgnoredAbandoned += count( $pluginsIgnoredAbandoned_perSites );
		$total_themesIgnoredAbandoned += count( $themesIgnoredAbandoned_perSites );

		//WP Upgrades part:
		$total_upgrades = $total_wp_upgrades + $total_plugin_upgrades + $total_theme_upgrades;
		if ( $globalView ) {
			$userExtension->site_view;
			?>
			<div class="mainwp-postbox-actions-top mainwp-padding-5">
				<div class="mainwp-cols-s mainwp-right mainwp-t-align-right">
					<form method="post" action="">
						<label for="mainwp_select_options_siteview"><?php _e( 'View Updates per: ', 'mainwp' ); ?></label>
						<select class="mainwp-select2" id="mainwp_select_options_siteview" name="select_mainwp_options_siteview">
							<option value="1" <?php echo $userExtension->site_view == 1 ? 'selected' : ''; ?>><?php esc_html_e( 'Site', 'mainwp' ); ?></option>
							<option value="0" <?php echo $userExtension->site_view == 0 ? 'selected' : ''; ?>><?php esc_html_e( 'Plugin/Theme', 'mainwp' ); ?></option>
						</select>
					</form>
				</div>
				<div class="mainwp-clear"></div>
			</div>
			<?php
		}
		?>
		<?php
		if ( $total_upgrades == 0 ) {
			$mainwp_tu_color_code = 'mainwp-green';
		} else if ( $total_upgrades > 0 && $total_upgrades < 5 ) {
			$mainwp_tu_color_code = 'mainwp-yellow';
		} else {
			$mainwp_tu_color_code = 'mainwp-red';
		}

		if ( $total_wp_upgrades == 0 ) {
			$mainwp_wp_color_code = 'mainwp-green';
		} else if ( $total_wp_upgrades > 0 && $total_wp_upgrades < 5 ) {
			$mainwp_wp_color_code = 'mainwp-yellow';
		} else {
			$mainwp_wp_color_code = 'mainwp-red';
		}

		if ( $total_translation_upgrades == 0 ) {
			$mainwp_t_color_code = 'mainwp-green';
		} else if ( $total_translation_upgrades > 0 && $total_translation_upgrades < 5 ) {
			$mainwp_t_color_code = 'mainwp-yellow';
		} else {
			$mainwp_t_color_code = 'mainwp-red';
		}

		if ( $total_plugin_upgrades == 0 ) {
			$mainwp_p_color_code = 'mainwp-green';
		} else if ( $total_plugin_upgrades > 0 && $total_plugin_upgrades < 5 ) {
			$mainwp_p_color_code = 'mainwp-yellow';
		} else {
			$mainwp_p_color_code = 'mainwp-red';
		}

		if ( $total_theme_upgrades == 0 ) {
			$mainwp_th_color_code = 'mainwp-green';
		} else if ( $total_theme_upgrades > 0 && $total_theme_upgrades < 5 ) {
			$mainwp_th_color_code = 'mainwp-yellow';
		} else {
			$mainwp_th_color_code = 'mainwp-red';
		}

		if ( $total_plugins_outdate == 0 ) {
			$mainwp_ap_color_code = 'mainwp-green';
		} else if ( $total_plugins_outdate > 0 && $total_plugins_outdate < 5 ) {
			$mainwp_ap_color_code = 'mainwp-yellow';
		} else {
			$mainwp_ap_color_code = 'mainwp-red';
		}

		if ( $total_themes_outdate == 0 ) {
			$mainwp_at_color_code = 'mainwp-green';
		} else if ( $total_themes_outdate > 0 && $total_themes_outdate < 5 ) {
			$mainwp_at_color_code = 'mainwp-yellow';
		} else {
			$mainwp_at_color_code = 'mainwp-red';
		}

		$show_updates_title = __('Click to see available updates', 'mainwp');
		$visit_dashboard_title = __('Visit this Dashboard', 'mainwp');
		$see_ignored_title = __('Click here to see all ignored updates', 'mainwp');

		?>
		<div class="mainwp-row-top">
			<div id="mainwp-right-now-total-updates" class="mainwp-left mainwp-cols-2">
				<span class="fa-stack fa-lg">
					<i class="fa fa-circle fa-stack-2x <?php echo $mainwp_tu_color_code; ?>"></i>
					<strong class="fa-stack-1x mainwp-white" style="display: inline-block;"><?php echo $total_upgrades; ?></strong> 
				</span>
				<span class="fa-lg"><?php echo _n( 'Update', 'Updates', $total_upgrades, 'mainwp' ); ?> <?php _e( 'available', 'mainwp' ); ?></span>
			</div>
			<?php if ( mainwp_current_user_can( 'dashboard', 'update_wordpress' ) && mainwp_current_user_can( 'dashboard', 'update_plugins' ) && mainwp_current_user_can( 'dashboard', 'update_themes' ) ) { ?>
				<div class="mainwp-right mainwp-cols-2 mainwp-t-align-right"><?php if ( ( $total_upgrades ) == 0 ) { ?>
						<a class="button button-hero" disabled="disabled"><?php _e( 'Update Everything', 'mainwp' ); ?></a><?php } else { ?>
						<a href="#" onClick="return rightnow_global_upgrade_all();" class="mainwp-upgrade-button button-hero button"><?php _e( 'Update Everything', 'mainwp' ); ?></a><?php } ?>
				</div>
			<?php } ?>
			<div class="mainwp-clear"></div>
		</div>
		<div class="mainwp-row">
			<div class="mainwp-left mainwp-cols-2">
				<a href="#" id="mainwp_upgrades_show" title="<?php echo esc_attr($show_updates_title);?>" onClick="return rightnow_show('upgrades', true);">
				<span class="fa-stack fa-lg">
				<i class="fa fa-circle fa-stack-2x <?php echo $mainwp_wp_color_code; ?>"></i>
					<strong class="fa-stack-1x mainwp-white"><?php echo $total_wp_upgrades; ?></strong>
				</span>
					<?php echo _n( 'WordPress update', 'WordPress updates', $total_wp_upgrades, 'mainwp'); ?> <?php _e('available','mainwp'); ?>
				</a>
			</div>
			<div class="mainwp-right mainwp-cols-2 mainwp-t-align-right">
				<?php if ( mainwp_current_user_can( 'dashboard', 'update_wordpress' ) ) {
					if ( $total_wp_upgrades > 0 ) { ?>
						&nbsp;
						<a href="#" onClick="return rightnow_wordpress_global_upgrade_all();" class="button-primary"><?php echo _n( 'Update', 'Update All', $total_wp_upgrades, 'mainwp' ); ?></a>
					<?php }
				} ?>
			</div>
			<div class="mainwp-clear"></div>
		</div>
		<div id="wp_upgrades" style="display: none" class="mainwp-sub-section">
			<?php
			@MainWP_DB::data_seek( $websites, 0 );
			while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
				if ( $website->is_ignoreCoreUpdates ) {
					continue;
				}

				$wp_upgrades = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'wp_upgrades' ), true );
				if ( ( count( $wp_upgrades ) == 0 ) && ( $website->sync_errors == '' ) ) {
					continue;
				}

				?>
				<div class="mainwp-sub-row mainwp_wordpress_upgrade" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" updated="<?php echo ( count( $wp_upgrades ) > 0 ) ? '0' : '1'; ?>">
					<div class="mainwp-left mainwp-padding-top-5 mainwp-cols-3"><a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a><input type="hidden" id="wp_upgraded_<?php echo $website->id; ?>" value="<?php if ( count( $wp_upgrades ) > 0 ) {
							echo '0';
						} else {
							echo '1';
						} ?>"/>
					</div>
					<div class="mainwp-cols-3 mainwp-left mainwp-padding-top-5 wordpressInfo" id="wp_upgrade_<?php echo $website->id; ?>">
						<?php
						if ( count( $wp_upgrades ) > 0 ) {
							echo $wp_upgrades['current'] . ' to ' . $wp_upgrades['new'];
						} else {
							if ( $website->sync_errors != '' ) {
								echo __( 'Site not connected', 'mainwp' );
							} else {
								echo __( 'No updates available!', 'mainwp' );
							}
						}
						?>
					</div>
					<div class="mainwp-right mainwp-t-align-right mainwp-cols-3 wordpressAction">
						<div id="wp_upgradebuttons_<?php echo $website->id; ?>">
							<?php
							if ( mainwp_current_user_can( 'dashboard', 'update_wordpress' ) ) {
								if ( count( $wp_upgrades ) > 0 ) {
									?>
									<a href="#" class="mainwp-upgrade-button button" onClick="rightnow_upgrade(<?php echo $website->id; ?>)"><?php _e( 'Update', 'mainwp' ); ?></a>
									<?php
								}
							}
							?>
							&nbsp;
								<span class="mainwp-padding-top-5">
								<a href="<?php echo $website->url; ?>" target="_blank"><i class="fa fa-external-link" aria-hidden="true"></i> <?php _e( 'Open', 'mainwp' ); ?></a>
						</span>
						</div>
					</div>
					<div class="mainwp-clear"></div>
				</div>
				<?php
			}
			?>
		</div>


		<?php
		//WP plugin updates!                
		?>
		<div class="mainwp-clear">
			<div class="mainwp-row">
				<div class="mainwp-left mainwp-cols-2">
					<a href="#" id="mainwp_plugin_upgrades_show" title="<?php echo esc_attr($show_updates_title);?>" onClick="return rightnow_show('plugin_upgrades', true);">
					<span class="fa-stack fa-lg">
						<i class="fa fa-circle fa-stack-2x <?php echo $mainwp_p_color_code; ?>"></i>
						<strong class="fa-stack-1x mainwp-white"><?php echo $total_plugin_upgrades; ?> </strong>
					</span>
						<?php _e('Plugin update','mainwp'); ?><?php if ($total_plugin_upgrades <> 1) { ?>s<?php } ?> <?php _e('available','mainwp'); ?>
					</a>
				</div>
				<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right">
					<?php if (mainwp_current_user_can("dashboard", "update_plugins")) {  ?>
						<?php if ($total_plugin_upgrades > 0 && ($userExtension->site_view == 1)) { ?>&nbsp; <a href="#" onClick="return rightnow_plugins_global_upgrade_all();" class="button-primary"><?php echo _n('Update', 'Update All', $total_plugin_upgrades, 'mainwp'); ?></a><?php } else if ($total_plugin_upgrades > 0 && ($userExtension->site_view == 0)) { ?>&nbsp; <a href="#" onClick="return rightnow_plugins_global_upgrade_all();" class="button-primary"><?php echo _n('Update', 'Update All', $total_plugin_upgrades, 'mainwp'); ?></a>
						<?php } } ?>
				</div>
				<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right mainwp-padding-top-5">
					<a href="<?php echo admin_url( 'admin.php?page=PluginsIgnore' ); ?>" title="<?php echo esc_attr($see_ignored_title);?>"><?php _e( 'Ignored', 'mainwp' ); ?> (<?php echo $total_pluginsIgnored; ?>)</a>
				</div>
				<div class="mainwp-clear"></div>
			</div>
			<div id="wp_plugin_upgrades" style="display: none" class="mainwp-sub-section">
				<?php
				if ( $userExtension->site_view == 1 ) {
					@MainWP_DB::data_seek( $websites, 0 );
					while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
						if ( $website->is_ignorePluginUpdates ) {
							continue;
						}

						$plugin_upgrades        = json_decode( $website->plugin_upgrades, true );
						$decodedPremiumUpgrades = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'premium_upgrades' ), true );
						if ( is_array( $decodedPremiumUpgrades ) ) {
							foreach ( $decodedPremiumUpgrades as $crrSlug => $premiumUpgrade ) {
								$premiumUpgrade['premium'] = true;

								if ( $premiumUpgrade['type'] == 'plugin' ) {
									if ( ! is_array( $plugin_upgrades ) ) {
										$plugin_upgrades = array();
									}
									$plugin_upgrades[ $crrSlug ] = $premiumUpgrade;
								}
							}
						}

						$ignored_plugins = json_decode( $website->ignored_plugins, true );
						if ( is_array( $ignored_plugins ) ) {
							$plugin_upgrades = array_diff_key( $plugin_upgrades, $ignored_plugins );
						}

						$ignored_plugins = json_decode( $userExtension->ignored_plugins, true );
						if ( is_array( $ignored_plugins ) ) {
							$plugin_upgrades = array_diff_key( $plugin_upgrades, $ignored_plugins );
						}

						if ( $globalView ) {
							?>
							<div class="mainwp-sub-row">
								<div class="mainwp-left mainwp-cols-3 mainwp-padding-top-5"><a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a><input type="hidden" id="wp_upgraded_plugin_<?php echo $website->id; ?>" value="<?php if ( count( $plugin_upgrades ) > 0 ) {
										echo '0';
									} else {
										echo '1';
									} ?>"/>
								</div>
								<div class="mainwp-left mainwp-cols-5 mainwp-padding-top-5" id="wp_upgrade_plugin_<?php echo $website->id; ?>">
									<?php
									if ( count( $plugin_upgrades ) > 0 ) {
										?>
										<a href="#" id="mainwp_plugin_upgrades_<?php echo $website->id; ?>_show" title="<?php echo esc_attr($show_updates_title);?>" onClick="return rightnow_show('plugin_upgrades_<?php echo $website->id; ?>', true);"> <?php echo count( $plugin_upgrades ); ?> <?php echo _n( 'Update', 'Updates', count($plugin_upgrades), 'mainwp' ); ?></a>
										<?php
									} else {
										if ( $website->sync_errors != '' ) {
											echo __( 'Site not connected', 'mainwp' );
										} else {
											echo __( 'No updates available!', 'mainwp' );
										}
									}
									?>
								</div>
								<div class="mainwp-right mainwp-cols-3 mainwp-t-align-right">
									<div id="wp_upgradebuttons_plugin_<?php echo $website->id; ?>">
										<?php
										if ( mainwp_current_user_can( 'dashboard', 'update_plugins' ) ) {
											if ( count( $plugin_upgrades ) > 0 ) { ?>
												<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_upgrade_plugin_all(<?php echo $website->id; ?>)"><?php echo _n( 'Update', 'Update All', count( $plugin_upgrades ), 'mainwp' ); ?></a> &nbsp;
											<?php } ?>
										<?php } ?>
										<span class="mainwp-padding-top-5">
										<a href="<?php echo $website->url; ?>" target="_blank"><i class="fa fa-external-link" aria-hidden="true"></i> <?php _e( 'Open', 'mainwp' ); ?></a>
                                                                                </span>
									</div>
								</div>
								<div class="mainwp-clear"></div>
							</div>
							<?php
						}
						?>
						<div id="wp_plugin_upgrades_<?php echo $website->id; ?>" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
							<?php
							foreach ( $plugin_upgrades as $plugin_name => $plugin_upgrade ) {
								$plugin_name = urlencode( $plugin_name );
								?>
								<div class="mainwp-sub-row" plugin_slug="<?php echo $plugin_name; ?>" premium="<?php echo ( isset( $plugin_upgrade['premium'] ) ? $plugin_upgrade['premium'] : 0 ) ? 1 : 0; ?>" updated="0">
									<div class="mainwp-left mainwp-padding-top-5 mainwp-cols-3">
										<?php if ( $globalView ) { ?>&nbsp;&nbsp;&nbsp;<?php } ?>
										<a href="<?php echo admin_url() . 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_upgrade['update']['slug'] . '&url=' . ( isset( $plugin_upgrade['PluginURI'] ) ? rawurlencode( $plugin_upgrade['PluginURI'] ) : '' ) . '&name=' . rawurlencode( $plugin_upgrade['Name'] ) . '&TB_iframe=true&width=640&height=477'; ?>" target="_blank" class="thickbox" title="More information about <?php echo $plugin_upgrade['Name']; ?>">
											<?php echo $plugin_upgrade['Name']; ?>
										</a>
										<input type="hidden" id="wp_upgraded_plugin_<?php echo $website->id; ?>_<?php echo $plugin_name; ?>" value="0"/>
									</div>
									<div class="mainwp-left mainwp-padding-top-5 mainwp-cols-5 pluginsInfo" id="wp_upgrade_plugin_<?php echo $website->id; ?>_<?php echo $plugin_name; ?>">
										<?php echo $plugin_upgrade['Version']; ?> to <?php echo $plugin_upgrade['update']['new_version']; ?>
									</div>
									<!--									<div class="mainwp-right mainwp-cols-3 mainwp-t-align-right pluginsAction">-->
									<div id="wp_upgradebuttons_plugin_<?php echo $website->id; ?>_<?php echo $plugin_name; ?>" site_id="<?php echo $website->id; ?>" >
										<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right pluginsAction">
											<?php if ( mainwp_current_user_can( 'dashboard', 'update_plugins' ) ) { ?>
												&nbsp;
												<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_upgrade_plugin(<?php echo $website->id; ?>, '<?php echo $plugin_name; ?>')"><?php _e( 'Update', 'mainwp' ); ?></a>
											<?php } ?>
										</div>
										<div class="mainwp-right mainwp-cols-6 mainwp-t-align-right pluginsAction">
											<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
												<a href="#" onClick="return rightnow_plugins_ignore_detail('<?php echo $plugin_name; ?>', '<?php echo urlencode( $plugin_upgrade['Name'] ); ?>', <?php echo $website->id; ?>)" class="button"><?php _e( 'Ignore', 'mainwp' ); ?></a>
											<?php } ?>
										</div>
									</div>
									<!--                                                                        </div>-->
									<div class="mainwp-clear"></div>
								</div>
							<?php }
							?>
						</div>
						<?php
					}
				} else {
					foreach ( $allPlugins as $slug => $val ) {
						$cnt = $val['cnt'];
						$plugin_name = urlencode( $slug );
						if ( $globalView ) {
							?>
							<div class="mainwp-sub-row">
								<div class="mainwp-left mainwp-cols-3 mainwp-padding-top-5">
									<a href="<?php echo admin_url() . 'plugin-install.php?tab=plugin-information&plugin=' . $pluginsInfo[ $slug ]['slug'] . '&url=' . ( isset( $pluginsInfo[ $slug ]['uri'] ) ? rawurlencode( $pluginsInfo[ $slug ]['uri'] ) : '' ) . '&name=' . rawurlencode( $pluginsInfo[ $slug ]['name'] ) . '&TB_iframe=true&width=640&height=477'; ?>" target="_blank"
									   class="thickbox" title="More information about <?php echo $pluginsInfo[ $slug ]['name']; ?>">
										<?php echo $pluginsInfo[ $slug ]['name']; ?>
									</a>
								</div>
								<div class="mainwp-left mainwp-padding-top-5">
									<a href="#" onClick="return rightnow_plugins_detail('<?php echo $plugin_name; ?>');" title="<?php echo esc_attr($show_updates_title);?>">
										<?php echo $cnt; ?> <?php _e( 'Update', 'mainwp' ); ?><?php echo( $cnt > 1 ? 's' : '' ); ?>
									</a>
								</div>
								<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right">
									<?php if ( mainwp_current_user_can( 'dashboard', 'update_plugins' ) ) { ?>
										&nbsp; <?php if ( $cnt > 0 ) { ?>
											<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_plugins_upgrade_all('<?php echo $plugin_name; ?>', '<?php echo urlencode( $pluginsInfo[ $slug ]['name'] ); ?>')"><?php echo _n( 'Update', 'Update All', $cnt, 'mainwp' ); ?></a>
										<?php } ?>
									<?php } ?>
								</div>
								<div class="mainwp-right mainwp-t-align-right mainwp-cols-4">
									<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
										<a href="#" class="button" onClick="return rightnow_plugins_ignore_all('<?php echo $plugin_name; ?>', '<?php echo urlencode( $pluginsInfo[ $slug ]['name'] ); ?>')"><?php _e( 'Ignore Globally', 'mainwp' ); ?></a>
									<?php } ?>
								</div>
								<div class="mainwp-clear"></div>
							</div>
							<?php
						}
						?>
						<div plugin_slug="<?php echo $plugin_name; ?>" plugin_name="<?php echo urlencode( $pluginsInfo[ $slug ]['name'] ); ?>" premium="<?php echo $pluginsInfo[ $slug ]['premium'] ? 1 : 0; ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
							<?php
							@MainWP_DB::data_seek( $websites, 0 );
							while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
								if ( $website->is_ignorePluginUpdates ) {
									continue;
								}
								$plugin_upgrades        = json_decode( $website->plugin_upgrades, true );
								$decodedPremiumUpgrades = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'premium_upgrades' ), true );
								if ( is_array( $decodedPremiumUpgrades ) ) {
									foreach ( $decodedPremiumUpgrades as $crrSlug => $premiumUpgrade ) {
										$premiumUpgrade['premium'] = true;

										if ( $premiumUpgrade['type'] == 'plugin' ) {
											if ( ! is_array( $plugin_upgrades ) ) {
												$plugin_upgrades = array();
											}
											$plugin_upgrades[ $crrSlug ] = $premiumUpgrade;
										}
									}
								}

								$ignored_plugins = json_decode( $website->ignored_plugins, true );
								if ( is_array( $ignored_plugins ) ) {
									$plugin_upgrades = array_diff_key( $plugin_upgrades, $ignored_plugins );
								}

								if ( ! isset( $plugin_upgrades[ $slug ] ) ) {
									continue;
								}

								$plugin_upgrade = $plugin_upgrades[ $slug ];
								?>
								<div class="mainwp-sub-row" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" updated="0">
									<div class="mainwp-left mainwp-padding-top-5 mainwp-cols-3">
										<?php if ( $globalView ) { ?>
											&nbsp;&nbsp;&nbsp;
											<a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a>
										<?php } else { ?>
											<a href="<?php echo admin_url() . 'plugin-install.php?tab=plugin-information&plugin=' . $pluginsInfo[ $slug ]['slug'] . '&TB_iframe=true&width=640&height=477'; ?>" target="_blank"
											   class="thickbox" title="More information about <?php echo $pluginsInfo[ $slug ]['name']; ?>">
												<?php echo $pluginsInfo[ $slug ]['name']; ?>
											</a>
										<?php } ?>
									</div>
									<div class="mainwp-left mainwp-padding-top-5 mainwp-cols-5 pluginsInfo">
										<?php echo $plugin_upgrade['Version']; ?> to <?php echo $plugin_upgrade['update']['new_version']; ?>
									</div>
									<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right pluginsAction">
										<?php if ( mainwp_current_user_can( 'dashboard', 'update_plugins' ) ) { ?>
											&nbsp;
											<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_plugins_upgrade('<?php echo $plugin_name; ?>', <?php echo $website->id; ?>)"><?php _e( 'Update', 'mainwp' ); ?></a>
										<?php } ?>
									</div>
									<div class="mainwp-right mainwp-cols-6 mainwp-t-align-right pluginsAction">
										<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
											<a href="#" class="button" onClick="return rightnow_plugins_ignore_detail('<?php echo $plugin_name; ?>', '<?php echo urlencode( $plugin_upgrade['Name'] ); ?>', <?php echo $website->id; ?>)"><?php _e( 'Ignore', 'mainwp' ); ?></a>
										<?php } ?>
									</div>
									<div class="mainwp-clear"></div>
								</div>
								<?php
							}
							?>
						</div>
						<?php
					}
				}
				?>
			</div>
		</div>

		<?php
		//WP theme updates!                
		?>
		<div class="mainwp-clear">
			<div class="mainwp-row">
				<div class="mainwp-left mainwp-cols-2">
					<a href="#" id="mainwp_theme_upgrades_show" title="<?php echo esc_attr($show_updates_title);?>"  onClick="return rightnow_show('theme_upgrades', true);">
					<span class="fa-stack fa-lg">
						<i class="fa fa-circle fa-stack-2x <?php echo $mainwp_th_color_code; ?>"></i>
						<strong class="fa-stack-1x mainwp-white"><?php echo $total_theme_upgrades; ?></strong> 
					</span><?php _e(' Theme update','mainwp'); ?><?php if ($total_theme_upgrades <> 1) { ?>s<?php } ?> <?php _e('available','mainwp'); ?>
					</a>
				</div>
				<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right">
					<?php if ( mainwp_current_user_can( 'dashboard', 'update_themes' ) ) { ?>
						<?php if ( $total_theme_upgrades > 0 && ( $userExtension->site_view == 1 ) ) { ?>&nbsp;
							<a href="#" onClick="return rightnow_themes_global_upgrade_all();" class="button-primary"><?php echo _n( 'Update', 'Update All', $total_theme_upgrades, 'mainwp' ); ?></a><?php } else if ( $total_theme_upgrades > 0 && ( $userExtension->site_view == 0 ) ) { ?>&nbsp;
							<a href="#" onClick="return rightnow_themes_global_upgrade_all();" class="button-primary"><?php echo _n( 'Update', 'Update All', $total_theme_upgrades, 'mainwp' ); ?></a><?php } ?>
					<?php } ?>
				</div>
				<div class="mainwp-cols-4 mainwp-right mainwp-t-align-right mainwp-padding-top-5">
					<a href="<?php echo admin_url( 'admin.php?page=ThemesIgnore' ); ?>" title="<?php echo esc_attr($see_ignored_title);?>"><?php _e( 'Ignored', 'mainwp' ); ?> (<?php echo $total_themesIgnored; ?>)</a>
				</div>
				<div class="mainwp-clear"></div>
			</div>
			<div id="wp_theme_upgrades" style="display: none" class="mainwp-sub-section">
				<?php
				if ( $userExtension->site_view == 1 ) {
					@MainWP_DB::data_seek( $websites, 0 );
					while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
						if ( $website->is_ignoreThemeUpdates ) {
							continue;
						}

						$theme_upgrades         = json_decode( $website->theme_upgrades, true );
						$decodedPremiumUpgrades = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'premium_upgrades' ), true );
						if ( is_array( $decodedPremiumUpgrades ) ) {
							foreach ( $decodedPremiumUpgrades as $crrSlug => $premiumUpgrade ) {
								$premiumUpgrade['premium'] = true;

								if ( $premiumUpgrade['type'] == 'theme' ) {
									if ( ! is_array( $theme_upgrades ) ) {
										$theme_upgrades = array();
									}
									$theme_upgrades[ $crrSlug ] = $premiumUpgrade;
								}
							}
						}

						$ignored_themes = json_decode( $website->ignored_themes, true );
						if ( is_array( $ignored_themes ) ) {
							$theme_upgrades = array_diff_key( $theme_upgrades, $ignored_themes );
						}

						$ignored_themes = json_decode( $userExtension->ignored_themes, true );
						if ( is_array( $ignored_themes ) ) {
							$theme_upgrades = array_diff_key( $theme_upgrades, $ignored_themes );
						}
						if ( $globalView ) {
							?>
							<div class="mainwp-sub-row">
								<div class="mainwp-left mainwp-cols-3 mainwp-padding-top-5"><a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a><input type="hidden" id="wp_upgraded_theme_<?php echo $website->id; ?>" value="<?php if ( count( $theme_upgrades ) > 0 ) {
										echo '0';
									} else {
										echo '1';
									} ?>"/>
								</div>
								<div class="mainwp-cols-5 mainwp-left" id="wp_upgrade_theme_<?php echo $website->id; ?>">
									<?php
									if ( count( $theme_upgrades ) > 0 ) {
										?>
										<a href="#" id="mainwp_theme_upgrades_<?php echo $website->id; ?>_show" title="<?php echo esc_attr($show_updates_title);?>" onClick="return rightnow_show('theme_upgrades_<?php echo $website->id; ?>', true);"> <?php echo count( $theme_upgrades ); ?> <?php echo _n( 'Update', 'Updates', count( $theme_upgrades ), 'mainwp' ); ?></a>
										<?php
									} else {
										if ( $website->sync_errors != '' ) {
											echo __( 'Site not connected', 'mainwp' );
										} else {
											echo __( 'No updates available!', 'mainwp' );
										}
									}
									?>
								</div>
								<div class="mainwp-right mainwp-cols-3 mainwp-t-align-right">
									<div id="wp_upgradebuttons_theme_<?php echo $website->id; ?>">
										<?php if ( mainwp_current_user_can( 'dashboard', 'update_themes' ) ) { ?>
											<?php if ( count( $theme_upgrades ) > 0 ) { ?>
												<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_upgrade_theme_all(<?php echo $website->id; ?>)"><?php echo _n( 'Update', 'Update All', count( $theme_upgrades ), 'mainwp' ); ?></a> &nbsp;
											<?php } ?>
										<?php } ?>
										<span class="mainwp-padding-top-5">
				                        <a href="<?php echo $website->url; ?>" target="_blank"><i class="fa fa-external-link" aria-hidden="true"></i> <?php _e( 'Open', 'mainwp' ); ?></a>
                    </span>
									</div>
								</div>
								<div class="mainwp-clear"></div>
							</div>
							<?php
						}
						?>
						<div id="wp_theme_upgrades_<?php echo $website->id; ?>" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
							<?php
							foreach ( $theme_upgrades as $theme_name => $theme_upgrade ) {
								$theme_name = urlencode( $theme_name );
								?>
								<div class="mainwp-sub-row" theme_slug="<?php echo $theme_name; ?>" theme_name="<?php echo $theme_upgrade['Name']; ?>" premium="<?php echo ( isset( $themesInfo[ $theme_name ]['premium'] ) && $themesInfo[ $theme_name ]['premium'] ) ? 1 : 0; ?>" updated="0">
									<div class="mainwp-left mainwp-padding-top-5 mainwp-cols-3"><?php if ( $globalView ) { ?>&nbsp;&nbsp;&nbsp;<?php } ?><?php echo $theme_upgrade['Name']; ?>
										<input type="hidden" id="wp_upgraded_theme_<?php echo $website->id; ?>_<?php echo $theme_name; ?>" value="0"/>
									</div>
									<div class="mainwp-left mainwp-cols-5 mainwp-padding-top-5 pluginsInfo" id="wp_upgrade_theme_<?php echo $website->id; ?>_<?php echo $theme_name; ?>">
										<?php echo $theme_upgrade['Version']; ?> to <?php echo $theme_upgrade['update']['new_version']; ?>
									</div>
									<!--                            		<div class="mainwp-right mainwp-cols-3 mainwp-t-align-right pluginsAction">-->
									<div id="wp_upgradebuttons_theme_<?php echo $website->id; ?>_<?php echo $theme_name; ?>" site_id="<?php echo $website->id; ?>">
										<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right pluginsAction">
											<?php if ( mainwp_current_user_can( 'dashboard', 'update_themes' ) ) { ?>
												&nbsp;
												<a href="#" class="mainwp-upgrade-button button" onClick="rightnow_upgrade_theme(<?php echo $website->id; ?>, '<?php echo $theme_name; ?>')"><?php _e( 'Update', 'mainwp' ); ?></a>
											<?php } ?>
										</div>
										<div class="mainwp-right mainwp-cols-6 mainwp-t-align-right pluginsAction">
											<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
												<a href="#" class="button" onClick="return rightnow_themes_ignore_detail('<?php echo $theme_name; ?>', '<?php echo urlencode( $theme_upgrade['Name'] ); ?>', <?php echo $website->id; ?>)"><?php _e( 'Ignore', 'mainwp' ); ?></a>
											<?php } ?>
										</div>
									</div>
									<!--                                                </div>-->
									<div class="mainwp-clear"></div>
								</div>
							<?php } ?>
						</div>
						<?php
					}
				} else {
					foreach ( $allThemes as $slug => $val ) {
						$cnt = $val['cnt'];
						$theme_name = urlencode( $slug );
						if ( $globalView ) {
							?>
							<div class="mainwp-sub-row">
								<div class="mainwp-left mainwp-cols-3 mainwp-padding-top-5">
									<?php echo $themesInfo[ $slug ]['name']; ?>
								</div>
								<div class="mainwp-left mainwp-padding-top-5">
									<a href="#" onClick="return rightnow_themes_detail('<?php echo $theme_name; ?>');">
										<?php echo $cnt; ?> <?php echo _n( 'Update', 'Updates', $cnt, 'mainwp' ); ?></a>
								</div>
								<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right">
									<?php if ( mainwp_current_user_can( 'dashboard', 'update_themes' ) ) { ?>
										<?php if ( $cnt > 0 ) { ?>
											<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_themes_upgrade_all('<?php echo $theme_name; ?>', '<?php echo urlencode( $themesInfo[ $slug ]['name'] ); ?>')"><?php echo _n( 'Update', 'Update All', $cnt, 'mainwp' ); ?></a><?php } else { ?> &nbsp;
											<a class="button" disabled="disabled"><?php _e( 'No Updates', 'mainwp' ); ?></a> <?php } ?>
									<?php } ?>
								</div>
								<div class="mainwp-right mainwp-t-align-right mainwp-cols-4">
									<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
										<a href="#" class="button" onClick="return rightnow_themes_ignore_all('<?php echo $theme_name; ?>', '<?php echo urlencode( $themesInfo[ $slug ]['name'] ); ?>')"><?php _e( 'Ignore Globally', 'mainwp' ); ?></a>
									<?php } ?>
								</div>
								<div class="mainwp-clear"></div>
							</div>
							<?php
						}
						?>
						<div theme_slug="<?php echo $theme_name; ?>" theme_name="<?php echo urlencode( $themesInfo[ $slug ]['name'] ); ?>" premium="<?php echo $themesInfo[ $slug ]['premium'] ? 1 : 0; ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
							<?php
							@MainWP_DB::data_seek( $websites, 0 );
							while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
								if ( $website->is_ignoreThemeUpdates ) {
									continue;
								}

								$theme_upgrades         = json_decode( $website->theme_upgrades, true );
								$decodedPremiumUpgrades = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'premium_upgrades' ), true );
								if ( is_array( $decodedPremiumUpgrades ) ) {
									foreach ( $decodedPremiumUpgrades as $crrSlug => $premiumUpgrade ) {
										$premiumUpgrade['premium'] = true;

										if ( $premiumUpgrade['type'] == 'theme' ) {
											if ( ! is_array( $theme_upgrades ) ) {
												$theme_upgrades = array();
											}
											$theme_upgrades[ $crrSlug ] = $premiumUpgrade;
										}
									}
								}

								$ignored_themes = json_decode( $website->ignored_themes, true );
								if ( is_array( $ignored_themes ) ) {
									$theme_upgrades = array_diff_key( $theme_upgrades, $ignored_themes );
								}

								if ( ! isset( $theme_upgrades[ $slug ] ) ) {
									continue;
								}

								$theme_upgrade = $theme_upgrades[ $slug ];
								?>
								<div class="mainwp-row" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" updated="0">
									<div class="mainwp-left mainwp-padding-top-5 mainwp-cols-3">
										<?php if ( $globalView ) { ?>
											&nbsp;&nbsp;&nbsp;
											<a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a>
										<?php } else {
											echo $themesInfo[ $slug ]['name'];
										} ?>
									</div>
									<div class="mainwp-left mainwp-cols-5 mainwp-padding-top-5 pluginsInfo">
										<?php echo $theme_upgrade['Version']; ?> to <?php echo $theme_upgrade['update']['new_version']; ?>
									</div>
									<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right pluginsAction">
										<?php if ( mainwp_current_user_can( 'dashboard', 'update_themes' ) ) { ?>
											&nbsp;
											<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_themes_upgrade('<?php echo $theme_name; ?>', <?php echo $website->id; ?>)"><?php _e( 'Update', 'mainwp' ); ?></a>
										<?php } ?>
									</div>
									<div class="mainwp-right mainwp-cols-6 mainwp-t-align-right pluginsAction">
										<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
											<a href="#" class="button" onClick="return rightnow_themes_ignore_detail('<?php echo $theme_name; ?>', '<?php echo urlencode( $theme_upgrade['Name'] ); ?>', <?php echo $website->id; ?>)"><?php _e( 'Ignore', 'mainwp' ); ?></a>
										<?php } ?>
									</div>
									<div class="mainwp-clear"></div>
								</div>
								<?php
							}
							?>
						</div>
						<?php
					}
				}
				?>
			</div>
		</div>

		<?php

		//Translation updates!
		$mainwp_show_language_updates = get_option( 'mainwp_show_language_updates', 1 );
		if ( $mainwp_show_language_updates == 1 ) {
			?>
			<div class="mainwp-row">
				<div class="mainwp-left mainwp-cols-2">
					<a href="#" id="mainwp_translation_upgrades_show" title="<?php echo esc_attr($show_updates_title);?>" onClick="return rightnow_show('translation_upgrades', true);">
					<span class="fa-stack fa-lg">
						<i class="fa fa-circle fa-stack-2x <?php echo $mainwp_t_color_code; ?>"></i>
						<strong class="fa-stack-1x mainwp-white"><?php echo $total_translation_upgrades; ?></strong>
					</span>
						<?php echo _n( 'Translation update', 'Translation updates', $total_wp_upgrades, 'mainwp') ?> <?php _e('available','mainwp'); ?>
					</a>&nbsp;<?php MainWP_Utility::renderToolTip(__('If you have non-English WordPress installations on your child sites, available Translation updates can be managed from this line. To disable the Translation Updates, go to the Settings page and disable the option in the Updates Option box.','mainwp'), 'https://make.wordpress.org/polyglots/handbook/about/what-we-do/', 'images/info.png', 'float: none !important;'); ?>
				</div>
				<div class="mainwp-right mainwp-cols-2 mainwp-t-align-right">
					<?php if (mainwp_current_user_can("dashboard", "update_translations")) {  ?><?php if ($total_translation_upgrades > 0 && ($userExtension->site_view == 1)) { ?>&nbsp; <a href="#" onClick="return rightnow_translations_global_upgrade_all();" class="button-primary"><?php echo _n('Update', 'Update All', $total_translation_upgrades, 'mainwp'); ?></a><?php } else if ($total_translation_upgrades > 0 && ($userExtension->site_view == 0)) { ?>&nbsp; <a href="#" onClick="return rightnow_translations_global_upgrade_all();" class="button-primary"><?php echo _n('Update', 'Update All', $total_translation_upgrades, 'mainwp'); ?></a><?php } }?>
				</div>
				<div class="mainwp-clear"></div>
			</div>
			<div id="wp_translation_upgrades" style="display: none" class="mainwp-sub-section">
				<?php
				if ( $userExtension->site_view == 1 ) {
					@MainWP_DB::data_seek( $websites, 0 );
					while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
//						if ( $website->is_ignoreTranslationUpdates ) {
//							continue;
//						}
//
						$translation_upgrades        = json_decode( $website->translation_upgrades, true );

//						$ignored_translations = json_decode( $website->ignored_translations, true );
//						if ( is_array( $ignored_translations ) ) {
//							$translation_upgrades = array_diff_key( $translation_upgrades, $ignored_translations );
//						}

//						$ignored_translations = json_decode( $userExtension->ignored_translations, true );
//						if ( is_array( $ignored_translations ) ) {
//							$translation_upgrades = array_diff_key( $translation_upgrades, $ignored_translations );
//						}

						if ( $globalView ) {
							?>
							<div class="mainwp-sub-row">
								<div class="mainwp-left mainwp-cols-3 mainwp-padding-top-5"><a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a><input type="hidden" id="wp_upgraded_translation_<?php echo $website->id; ?>" value="<?php if ( count( $translation_upgrades ) > 0 ) {
										echo '0';
									} else {
										echo '1';
									} ?>"/>
								</div>
								<div class="mainwp-left mainwp-cols-3 mainwp-padding-top-5 translationsInfo" id="wp_upgrade_translation_<?php echo $website->id; ?>">
									<?php
									if ( count( $translation_upgrades ) > 0 ) {
										?>
										<a href="#" id="mainwp_translation_upgrades_<?php echo $website->id; ?>_show" title="<?php echo esc_attr($show_updates_title);?>" onClick="return rightnow_show('translation_upgrades_<?php echo $website->id; ?>', true);"> <?php echo count( $translation_upgrades ); ?> <?php echo _n( 'Update', 'Updates', count($translation_upgrades), 'mainwp' ); ?></a>
										<?php
									} else {
										if ( $website->sync_errors != '' ) {
											echo __( 'Site not connected', 'mainwp' );
										} else {
											echo __( 'No updates available!', 'mainwp' );
										}
									}
									?>
								</div>
								<div class="mainwp-right mainwp-cols-3 mainwp-t-align-right translationsAction">
									<div id="wp_upgradebuttons_translation_<?php echo $website->id; ?>">
										<?php
										if ( mainwp_current_user_can( 'dashboard', 'update_translations' ) ) {
											if ( count( $translation_upgrades ) > 0 ) { ?>
												<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_upgrade_translation_all(<?php echo $website->id; ?>)"><?php echo _n( 'Update', 'Update All', count( $translation_upgrades ), 'mainwp' ); ?></a> &nbsp;
											<?php } ?>
										<?php } ?>
										<span class="mainwp-padding-top-5">
											<a href="<?php echo $website->url; ?>" target="_blank"><i class="fa fa-external-link" aria-hidden="true"></i> <?php _e( 'Open', 'mainwp' ); ?></a>
								</span>
									</div>
								</div>
								<div class="mainwp-clear"></div>
							</div>
							<?php
						}
						?>
						<div class="mainwp-sub-section" id="wp_translation_upgrades_<?php echo $website->id; ?>" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
							<?php
							foreach ( $translation_upgrades as $translation_upgrade) {
								$translation_name = isset( $translation_upgrade['name'] ) ? $translation_upgrade['name'] : $translation_upgrade['slug'];
								$translation_slug = $translation_upgrade['slug'];
								?>
								<div class="mainwp-sub-row" translation_slug="<?php echo $translation_slug; ?>" updated="0">
									<div class="mainwp-left mainwp-cols-3 mainwp-padding-top-5">
										<?php if ( $globalView ) { ?>&nbsp;&nbsp;&nbsp;<?php } ?>
										<?php echo $translation_name; ?>
										<input type="hidden" id="wp_upgraded_translation_<?php echo $website->id; ?>_<?php echo $translation_slug; ?>" value="0"/>
									</div>
									<div class="mainwp-left mainwp-cols-5 mainwp-padding-top-5 translationsInfo" id="wp_upgrade_translation_<?php echo $website->id; ?>_<?php echo $translation_slug; ?>">
										<?php echo $translation_upgrade['version']; ?>
									</div>
									<div class="mainwp-right mainwp-cols-3 mainwp-t-align-right translationsAction">
										<div id="wp_upgradebuttons_translation_<?php echo $website->id; ?>_<?php echo $translation_slug; ?>">
											<?php if ( mainwp_current_user_can( 'dashboard', 'update_translations' ) ) { ?>
												&nbsp;
												<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_upgrade_translation(<?php echo $website->id; ?>//, '<?php echo $translation_slug; ?>//')"><?php _e( 'Update', 'mainwp' ); ?></a>
											<?php } ?>
										</div>
									</div>
									<div class="mainwp-clear"></div>
								</div>
							<?php }
							?>
						</div>
						<?php
					}
				} else {
					foreach ( $allTranslations as $slug => $val ) {
						$cnt = $val['cnt'];
						if ( $globalView ) {
							?>
							<div class="mainwp-sub-row">
								<div class="mainwp-left mainwp-cols-3 mainwp-padding-top-5">
									<?php echo $translationsInfo[ $slug ]['name']; ?>
								</div>
								<div class="mainwp-left mainwp-cols-5 mainwp-padding-top-5 translationsInfo">
									<a href="#" onClick="return rightnow_translations_detail('<?php echo $slug; ?>');">
										<?php echo $cnt; ?> <?php _e( 'Update', 'mainwp' ); ?><?php echo( $cnt > 1 ? 's' : '' ); ?>
									</a>
								</div>
								<div class="mainwp-right mainwp-cols-3 mainwp-t-align-right translationsAction">
									<?php if ( mainwp_current_user_can( 'dashboard', 'update_translations' ) ) { ?>
										&nbsp; <?php if ( $cnt > 0 ) { ?>
											<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_translations_upgrade_all('<?php echo $slug; ?>', '<?php echo urlencode( $translationsInfo[ $slug ]['name'] ); ?>')"><?php echo _n( 'Update', 'Update All', $cnt, 'mainwp' ); ?></a>
										<?php } ?>
									<?php } ?>
								</div>
								<div class="mainwp-clear"></div>
							</div>
							<?php
						}
						?>
						<div translation_slug="<?php echo $slug; ?>" translation_name="<?php echo urlencode( $translationsInfo[ $slug ]['name'] ); ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
							<?php
							@MainWP_DB::data_seek( $websites, 0 );
							while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
//								if ( $website->is_ignoreTranslationUpdates ) {
//									continue;
//								}
								$translation_upgrades        = json_decode( $website->translation_upgrades, true );

								$translation_upgrade = null;
								foreach ( $translation_upgrades as $current_translation_upgrade ) {
									if ( $current_translation_upgrade['slug'] == $slug ) {
										$translation_upgrade = $current_translation_upgrade;
										break;
									}
								}

								if ( null === $translation_upgrade ) {
									continue;
								}

								?>
								<div class="mainwp-sub-row" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" updated="0">
									<div class="mainwp-left mainwp-cols-3 mainwp-padding-top-5">
										<?php if ( $globalView ) { ?>
											&nbsp;&nbsp;&nbsp;
											<a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a>
										<?php } else { ?>
											<?php echo $translationsInfo[ $slug ]['name']; ?>
										<?php } ?>
									</div>
									<div class="mainwp-left mainwp-cols-5 mainwp-padding-top-5 translationsInfo">
										<?php echo $translation_upgrade['version']; ?>
									</div>
									<div class="mainwp-right mainwp-cols-3 mainwp-t-align-right translationsAction">
										<?php if ( mainwp_current_user_can( 'dashboard', 'update_translations' ) ) { ?>
											&nbsp;
											<a href="#" class="mainwp-upgrade-button button" onClick="return rightnow_translations_upgrade('<?php echo $slug; ?>', <?php echo $website->id; ?>)"><?php _e( 'Update', 'mainwp' ); ?></a>
										<?php } ?>
									</div>
									<div class="mainwp-clear"></div>
								</div>
								<?php
							}
							?>
						</div>
						<?php
					}
				}
				?>
			</div>
			<?php
		}


		//WP plugin Abandoned!
		if (!$isUpdatesPage) {
			?>
			<div class="mainwp-clear">
				<div class="mainwp-row">
					<div class="mainwp-left mainwp-cols-2">
						<a href="#" id="mainwp_plugins_outdate_show" onClick="return rightnow_show('plugins_outdate', true);">
					<span class="fa-stack fa-lg">
					<i class="fa fa-circle fa-stack-2x <?php echo $mainwp_ap_color_code; ?>"></i>
						<strong class="fa-stack-1x mainwp-white"><?php echo $total_plugins_outdate; ?> </strong>
					</span>
							<?php echo _n( 'Plugin', 'Plugins', $total_plugins_outdate, 'mainwp'); ?> <?php _e('Possibly Abandoned', 'mainwp'); ?>
						</a>&nbsp;<?php MainWP_Utility::renderToolTip(__('This feature checks the last updated status of plugins and alerts you if not updated in a specific amount of time. This gives you insight on if a plugin may have been abandoned by the author.','mainwp'), 'http://docs.mainwp.com/what-does-possibly-abandoned-mean/', 'images/info.png', 'float: none !important;'); ?>
					</div>
					<div class="mainwp-left mainwp-cols-4 mainwp-padding-top-5">
						<a href="<?php echo admin_url( 'admin.php?page=PluginsIgnoredAbandoned' ); ?>"><?php _e( 'Ignored', 'mainwp' ); ?> (<?php echo $total_pluginsIgnoredAbandoned; ?>)</a>
					</div>
					<div class="mainwp-right mainwp-cols-4"></div>
					<div class="mainwp-clear"></div>
				</div>
				<div id="wp_plugins_outdate" style="display: none" class="mainwp-sub-section">
					<?php
					$str_format = __( 'Updated %s Days Ago', 'mainwp' );
					if ( $userExtension->site_view == 1 ) {
						@MainWP_DB::data_seek( $websites, 0 );
						while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
							$plugins_outdate = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'plugins_outdate_info' ), true );

							if ( ! is_array( $plugins_outdate ) ) {
								$plugins_outdate = array();
							}

							if ( count( $plugins_outdate ) > 0 ) {
								$pluginsOutdateDismissed = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'plugins_outdate_dismissed' ), true );
								if ( is_array( $pluginsOutdateDismissed ) ) {
									$plugins_outdate = array_diff_key( $plugins_outdate, $pluginsOutdateDismissed );
								}

								if ( is_array( $decodedDismissedPlugins ) ) {
									$plugins_outdate = array_diff_key( $plugins_outdate, $decodedDismissedPlugins );
								}
							}

							if ( $globalView ) {
								?>
								<div class="mainwp-sub-row">
									<div class="mainwp-left mainwp-cols-3"><a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a><input type="hidden" id="wp_upgraded_plugin_<?php echo $website->id; ?>" value="<?php if ( count( $plugins_outdate ) > 0 ) {
											echo '0';
										} else {
											echo '1';
										} ?>"/>
									</div>
									<div class="mainwp-left mainwp-cols-3" id="wp_outdate_plugin_<?php echo $website->id; ?>">
										<?php
										if ( count( $plugins_outdate ) > 0 ) {
											?>
											<a href="#" id="mainwp_plugins_outdate_<?php echo $website->id; ?>_show" onClick="return rightnow_show('plugins_outdate_<?php echo $website->id; ?>', true);"> <?php echo count( $plugins_outdate ); ?> <?php echo _n( 'Plugin', 'Plugins', count( $plugins_outdate ), 'mainwp' ); ?></a>
											<?php
										} else {
											if ( $website->sync_errors != '' ) {
												echo __( 'Site not connected', 'mainwp' );
											} else {
												echo __( 'No abandoned plugins!', 'mainwp' );
											}
										}
										?>
									</div>
									<div class="mainwp-right mainwp-padding-top-5 mainwp-cols-4 mainwp-t-align-right">
										<div id="wp_upgradebuttons_plugin_<?php echo $website->id; ?>">
											<a href="<?php echo $website->url; ?>" target="_blank"><i class="fa fa-external-link" aria-hidden="true"></i> <?php _e( 'Open', 'mainwp' ); ?></a>
										</div>
									</div>
									<div class="mainwp-clear"></div>
								</div>
								<?php
							}
							?>
							<div id="wp_plugins_outdate_<?php echo $website->id; ?>" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
								<?php
								foreach ( $plugins_outdate as $slug => $plugin_outdate ) {
									$plugin_name = urlencode( $slug );

									$now                      = new \DateTime();
									$last_updated             = $plugin_outdate['last_updated'];
									$plugin_last_updated_date = new \DateTime( '@' . $last_updated );
									$diff_in_days             = $now->diff( $plugin_last_updated_date )->format( '%a' );

									$outdate_notice = sprintf( $str_format, $diff_in_days );
									?>
									<div class="mainwp-sub-row" plugin_outdate_slug="<?php echo $plugin_name; ?>" dismissed="0">
										<div class="mainwp-left mainwp-cols-3">
											<?php if ( $globalView ) { ?>&nbsp;&nbsp;&nbsp;<?php } ?>
											<a href="<?php echo admin_url() . 'plugin-install.php?tab=plugin-information&plugin=' . dirname( $slug ) . '&url=' . ( isset( $plugin_outdate['PluginURI'] ) ? rawurlencode( $plugin_outdate['PluginURI'] ) : '' ) . '&name=' . rawurlencode( $plugin_outdate['Name'] ) . '&TB_iframe=true&width=640&height=477'; ?>" target="_blank"
											   class="thickbox" title="More information about <?php echo $plugin_outdate['Name']; ?>"><?php echo $plugin_outdate['Name']; ?></a><input type="hidden" id="wp_dismissed_plugin_<?php echo $website->id; ?>_<?php echo $plugin_name; ?>" value="0"/>
										</div>
										<div class="mainwp-left mainwp-cols-3 pluginsInfo" id="wp_outdate_plugin_<?php echo $website->id; ?>_<?php echo $plugin_name; ?>">
											<?php echo $plugin_outdate['Version']; ?> | <?php echo $outdate_notice; ?>
										</div>
										<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right pluginsAction">
											<div id="wp_dismissbuttons_plugin_<?php echo $website->id; ?>_<?php echo $plugin_name; ?>">
												<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
													&nbsp;
													<a href="#" class="button" onClick="return rightnow_plugins_dismiss_outdate_detail('<?php echo $plugin_name; ?>', '<?php echo urlencode( $plugin_outdate['Name'] ); ?>', <?php echo $website->id; ?>)"><?php _e( 'Ignore', 'mainwp' ); ?></a>
												<?php } ?>
											</div>
										</div>
										<div class="mainwp-clear"></div>
									</div>
								<?php }
								?>
							</div>
							<?php
						}
					} else {
						foreach ( $allPluginsOutdate as $slug => $val ) {
							$cnt = $val['cnt'];
							$plugin_name = urlencode( $slug );
							if ( $globalView ) {
								?>
								<div class="mainwp-sub-row">
									<div class="mainwp-left mainwp-cols-3">
										<a href="<?php echo admin_url() . 'plugin-install.php?tab=plugin-information&plugin=' . dirname( $slug ) . '&url=' . ( isset( $pluginsOutdateInfo[ $slug ]['uri-'] ) ? rawurlencode( $pluginsOutdateInfo[ $slug ]['uri'] ) : '' ) . '&name=' . rawurlencode( $pluginsOutdateInfo[ $slug ]['Name'] ) . '&TB_iframe=true&width=640&height=477'; ?>" target="_blank"
										   class="thickbox" title="More information about <?php echo $pluginsOutdateInfo[ $slug ]['Name']; ?>">
											<?php echo $pluginsOutdateInfo[ $slug ]['Name']; ?>
										</a>
									</div>
									<div class="mainwp-left mainwp-cols-3">
										<a href="#" onClick="return rightnow_plugins_outdate_detail('<?php echo $plugin_name; ?>');">
											<?php echo $cnt; ?> <?php echo _n( 'Plugin', 'Plugins', $cnt, 'mainwp' ); ?></a>
									</div>
									<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right">
										<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
											<a href="#" class="button" onClick="return rightnow_plugins_abandoned_ignore_all('<?php echo $plugin_name; ?>', '<?php echo urlencode( $pluginsOutdateInfo[ $slug ]['Name'] ); ?>')"><?php _e( 'Ignore Globally', 'mainwp' ); ?></a>
										<?php } ?>
									</div>
									<div class="mainwp-clear"></div>
								</div>
								<?php
							}
							?>
							<div plugin_outdate_slug="<?php echo $plugin_name; ?>" plugin_name="<?php echo urlencode( $pluginsOutdateInfo[ $slug ]['Name'] ); ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
								<?php
								@MainWP_DB::data_seek( $websites, 0 );
								while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
									$plugins_outdate = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'plugins_outdate_info' ), true );
									if ( ! is_array( $plugins_outdate ) ) {
										$plugins_outdate = array();
									}

									if ( count( $plugins_outdate ) > 0 ) {
										$pluginsOutdateDismissed = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'plugins_outdate_dismissed' ), true );
										if ( is_array( $pluginsOutdateDismissed ) ) {
											$plugins_outdate = array_diff_key( $plugins_outdate, $pluginsOutdateDismissed );
										}

										if ( is_array( $decodedDismissedPlugins ) ) {
											$plugins_outdate = array_diff_key( $plugins_outdate, $decodedDismissedPlugins );
										}
									}

									if ( ! isset( $plugins_outdate[ $slug ] ) ) {
										continue;
									}

									$plugin_outdate = $plugins_outdate[ $slug ];

									$now                      = new \DateTime();
									$last_updated             = $plugin_outdate['last_updated'];
									$plugin_last_updated_date = new \DateTime( '@' . $last_updated );
									$diff_in_days             = $now->diff( $plugin_last_updated_date )->format( '%a' );

									$outdate_notice = sprintf( $str_format, $diff_in_days );

									?>
									<div class="mainwp-sub-row" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" outdate="1">
										<div class="mainwp-left mainwp-cols-3">
											<?php if ( $globalView ) { ?>
												&nbsp;&nbsp;&nbsp;
												<a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a>
											<?php } else { ?>
												<a href="<?php echo admin_url() . 'plugin-install.php?tab=plugin-information&plugin=' . dirname( $slug ) . '&TB_iframe=true&width=640&height=477'; ?>" target="_blank"
												   class="thickbox" title="More information about <?php echo $pluginsOutdateInfo[ $slug ]['Name']; ?>">
													<?php echo $pluginsOutdateInfo[ $slug ]['Name']; ?>
												</a>
											<?php } ?>
										</div>
										<div class="mainwp-left mainwp-cols-3 pluginsInfo" id="wp_outdate_plugin_<?php echo $website->id; ?>_<?php echo $plugin_name; ?>">
											<?php echo $plugin_outdate['Version']; ?> | <?php echo $outdate_notice; ?>
										</div>
										<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right pluginsAction">
											<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
												&nbsp;
												<a href="#" class="button" onClick="return rightnow_plugins_dismiss_outdate_detail('<?php echo $plugin_name; ?>',  '<?php echo urlencode( $plugin_outdate['Name'] ); ?>', <?php echo $website->id; ?>)"><?php _e( 'Ignore', 'mainwp' ); ?></a>
											<?php } ?>
										</div>
										<div class="mainwp-clear"></div>
									</div>
									<?php
								}
								?>
							</div>
							<?php
						}
					}
					?>
				</div>
			</div>


			<?php
			//WP theme Abandoned!
			?>
			<div class="mainwp-clear">
				<div class="mainwp-row">
					<div class="mainwp-left mainwp-cols-2">
						<a href="#" id="mainwp_themes_outdate_show" onClick="return rightnow_show('themes_outdate', true);">
					<span class="fa-stack fa-lg">
						<i class="fa fa-circle fa-stack-2x <?php echo $mainwp_at_color_code; ?>"></i>
						<strong class="fa-stack-1x mainwp-white"><?php echo $total_themes_outdate; ?> </strong> 
					</span>
							<?php echo _n( 'Theme', 'Themes', $total_themes_outdate, 'mainwp'); ?> <?php _e('Possibly Abandoned', 'mainwp'); ?>
						</a>&nbsp;<?php MainWP_Utility::renderToolTip(__('This feature checks the last updated status of themes and alerts you if not updated in a specific amount of time. This gives you insight on if a theme may have been abandoned by the author.','mainwp'), 'http://docs.mainwp.com/what-does-possibly-abandoned-mean/', 'images/info.png', 'float: none !important;'); ?>
					</div>
					<div class="mainwp-left mainwp-cols-4 mainwp-padding-top-5">
						<a href="<?php echo admin_url( 'admin.php?page=ThemesIgnoredAbandoned' ); ?>"><?php _e( 'Ignored', 'mainwp' ); ?> (<?php echo $total_themesIgnoredAbandoned; ?>)</a>
					</div>
					<div class="mainwp-right mainwp-cols-4"></div>
					<div class="mainwp-clear"></div>
				</div>
				<div id="wp_themes_outdate" style="display: none" class="mainwp-sub-section">
					<?php
					if ( $userExtension->site_view == 1 ) {
						@MainWP_DB::data_seek( $websites, 0 );
						while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
							$themes_outdate = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'themes_outdate_info' ), true );

							if ( ! is_array( $themes_outdate ) ) {
								$themes_outdate = array();
							}

							if ( count( $themes_outdate ) > 0 ) {
								$themesOutdateDismissed = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'themes_outdate_dismissed' ), true );
								if ( is_array( $themesOutdateDismissed ) ) {
									$themes_outdate = array_diff_key( $themes_outdate, $themesOutdateDismissed );
								}

								if ( is_array( $decodedDismissedThemes ) ) {
									$themes_outdate = array_diff_key( $themes_outdate, $decodedDismissedThemes );
								}
							}

							if ( $globalView ) {
								?>
								<div class="mainwp-sub-row">
									<div class="mainwp-left mainwp-cols-3"><a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a><input type="hidden" id="wp_upgraded_theme_<?php echo $website->id; ?>" value="<?php if ( count( $themes_outdate ) > 0 ) {
											echo '0';
										} else {
											echo '1';
										} ?>"/>
									</div>
									<div class="mainwp-left mainwp-cols-3" id="wp_outdate_theme_<?php echo $website->id; ?>">
										<?php
										if ( count( $themes_outdate ) > 0 ) {
											?>
											<a href="#" id="mainwp_themes_outdate_<?php echo $website->id; ?>_show" onClick="return rightnow_show('themes_outdate_<?php echo $website->id; ?>', true);"> <?php echo count( $themes_outdate ); ?> <?php echo _n( 'Theme', 'Themes', count( $themes_outdate ), 'mainwp' ); ?></a>
											<?php
										} else {
											if ( $website->sync_errors != '' ) {
												echo __( 'Site not connected', 'mainwp' );
											} else {
												echo __( 'No abandoned themes!', 'mainwp' );
											}
										}
										?>
									</div>
									<div class="mainwp-right mainwp-cols-4 mainwp-padding-top-5 mainwp-t-align-right">
										<div id="wp_upgradebuttons_theme_<?php echo $website->id; ?>">
											<a href="<?php echo $website->url; ?>" target="_blank"><i class="fa fa-external-link" aria-hidden="true"></i> <?php _e( 'Open', 'mainwp' ); ?></a>
										</div>
									</div>
									<div class="mainwp-clear"></div>
								</div>
								<?php
							}
							?>
							<div id="wp_themes_outdate_<?php echo $website->id; ?>" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
								<?php
								foreach ( $themes_outdate as $slug => $theme_outdate ) {
									$slug                    = urlencode( $slug );
									$now                     = new \DateTime();
									$last_updated            = $theme_outdate['last_updated'];
									$theme_last_updated_date = new \DateTime( '@' . $last_updated );
									$diff_in_days            = $now->diff( $theme_last_updated_date )->format( '%a' );
									$outdate_notice          = sprintf( $str_format, $diff_in_days );
									?>
									<div class="mainwp-sub-row" theme_outdate_slug="<?php echo $slug; ?>" dismissed="0">
										<div class="mainwp-left mainwp-cols-3">
											<?php if ( $globalView ) { ?>&nbsp;&nbsp;&nbsp;<?php } ?><?php echo $theme_outdate['Name']; ?>
											<input type="hidden" id="wp_dismissed_theme_<?php echo $website->id; ?>_<?php echo $slug; ?>" value="0"/>
										</div>
										<div class="mainwp-left mainwp-cols-3 pluginsInfo" id="wp_outdate_theme_<?php echo $website->id; ?>_<?php echo $slug; ?>">
											<?php echo $theme_outdate['Version']; ?> | <?php echo $outdate_notice; ?>
										</div>
										<div class="mainwp-right mainwp-cols-3 mainwp-t-align-right pluginsAction">
											<div id="wp_dismissbuttons_theme_<?php echo $website->id; ?>_<?php echo $slug; ?>">
												<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
													&nbsp;
													<a href="#" class="button" onClick="return rightnow_themes_dismiss_outdate_detail('<?php echo $slug; ?>', '<?php echo urlencode( $theme_outdate['Name'] ); ?>', <?php echo $website->id; ?>)"><?php _e( 'Ignore', 'mainwp' ); ?></a>
												<?php } ?>
											</div>
										</div>
										<div class="mainwp-clear"></div>
									</div>
								<?php }
								?>
							</div>
							<?php
						}
					} else {
						foreach ( $allThemesOutdate as $slug => $val ) {
							$cnt = $val['cnt'];
							$slug = urlencode( $slug );

							if ( $globalView ) {
								?>
								<div class="mainwp-sub-row">
									<div class="mainwp-left mainwp-cols-3">
										<?php echo $themesOutdateInfo[ $slug ]['name']; ?>
									</div>
									<div class="mainwp-left mainwp-cols-3">
										<a href="#" onClick="return rightnow_themes_outdate_detail('<?php echo $slug; ?>');">
											<?php echo $cnt; ?> <?php echo _n( 'Theme', 'Themes', $cnt, 'mainwp' ); ?></a>
									</div>
									<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right">
										<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
											<a href="#" class="button" onClick="return rightnow_themes_abandoned_ignore_all('<?php echo $slug; ?>', '<?php echo urlencode( $themesOutdateInfo[ $slug ]['name'] ); ?>')"><?php _e( 'Ignore Globally', 'mainwp' ); ?></a>
										<?php } ?>
									</div>
									<div class="mainwp-clear"></div>
								</div>
								<?php
							}
							?>
							<div theme_outdate_slug="<?php echo $slug; ?>" theme_name="<?php echo urlencode( $themesOutdateInfo[ $slug ]['name'] ); ?>" <?php if ( $globalView ) { ?>style="display: none"<?php } ?>>
								<?php
								@MainWP_DB::data_seek( $websites, 0 );
								while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
									$themes_outdate = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'themes_outdate_info' ), true );
									if ( ! is_array( $themes_outdate ) ) {
										$themes_outdate = array();
									}

									if ( count( $themes_outdate ) > 0 ) {
										$themesOutdateDismissed = json_decode( MainWP_DB::Instance()->getWebsiteOption( $website, 'themes_outdate_dismissed' ), true );
										if ( is_array( $themesOutdateDismissed ) ) {
											$themes_outdate = array_diff_key( $themes_outdate, $themesOutdateDismissed );
										}

										if ( is_array( $decodedDismissedThemes ) ) {
											$themes_outdate = array_diff_key( $themes_outdate, $decodedDismissedThemes );
										}
									}

									if ( ! isset( $themes_outdate[ $slug ] ) ) {
										continue;
									}

									$theme_outdate = $themes_outdate[ $slug ];

									$now                     = new \DateTime();
									$last_updated            = $theme_outdate['last_updated'];
									$theme_last_updated_date = new \DateTime( '@' . $last_updated );
									$diff_in_days            = $now->diff( $theme_last_updated_date )->format( '%a' );
									$outdate_notice          = sprintf( $str_format, $diff_in_days );

									?>
									<div class="mainwp-sub-row" site_id="<?php echo $website->id; ?>" site_name="<?php echo rawurlencode( $website->name ); ?>" outdate="1">
										<div class="mainwp-left mainwp-cols-3">
											<?php if ( $globalView ) { ?>
												&nbsp;&nbsp;&nbsp;
												<a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a>
											<?php } else { ?>
												<?php echo $themesOutdateInfo[ $slug ]['name']; ?>
											<?php } ?>
										</div>
										<div class="mainwp-left mainwp-cols-3 pluginsInfo" id="wp_outdate_theme_<?php echo $website->id; ?>_<?php echo $slug; ?>">
											<?php echo $theme_outdate['Version']; ?> | <?php echo $outdate_notice; ?>
										</div>
										<div class="mainwp-right mainwp-cols-4 mainwp-t-align-right pluginsAction">
											<?php if ( mainwp_current_user_can( 'dashboard', 'ignore_unignore_updates' ) ) { ?>
												&nbsp;
												<a href="#" class="button" onClick="return rightnow_themes_dismiss_outdate_detail('<?php echo $slug; ?>',  '<?php echo urlencode( $themesOutdateInfo[ $slug ]['name'] ); ?>', <?php echo $website->id; ?>)"><?php _e( 'Ignore', 'mainwp' ); ?></a>
											<?php } ?>
										</div>
										<div class="mainwp-clear"></div>
									</div>
									<?php
								}
								?>
							</div>
							<?php
						}
					}
					?>
				</div>
			</div>
			<?php
		} // is update page

		//Good - some are up to date!
		if ( $total_uptodate > 0 ) {
			?>
			<div class="mainwp-clear">
				<div class="mainwp-row">
					<span class="mainwp-left-col">
						<a href="#" id="mainwp_uptodate_show" title="<?php echo esc_attr($show_updates_title);?>" onClick="return rightnow_show('uptodate', true);">
							<span class="mainwp-rightnow-number"><?php echo $total_uptodate; ?></span> <?php _e('Up to date','mainwp'); ?>
						</a>
					</span>
					<span class="mainwp-mid-col">&nbsp;</span>
					<span class="mainwp-right-col"></span>
				</div>
				<div id="wp_uptodate" style="display: none">
					<?php
					@MainWP_DB::data_seek( $websites, 0 );
					while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
						if ( $website->uptodate != 1 ) {
							continue;
						}
						?>
						<div class="mainwp-row">
							<span class="mainwp-left-col"><a href="<?php echo admin_url( 'admin.php?page=managesites&dashboard=' . $website->id ); ?>" title="<?php echo esc_attr($visit_dashboard_title);?>"><?php echo stripslashes( $website->name ); ?></a></span>
							<span class="mainwp-mid-col">&nbsp;</span>
							<span class="mainwp-right-col"><a href="<?php echo $website->url; ?>" target="_blank"><i class="fa fa-external-link" aria-hidden="true"></i> <?php _e( 'Open', 'mainwp' ); ?></a></span>
						</div>
					<?php } ?>
				</div>
			</div>
		<?php } ?>

		<?php
		@MainWP_DB::data_seek( $websites, 0 );
		$site_ids = array();
		while ( $websites && ( $website = @MainWP_DB::fetch_object( $websites ) ) ) {
			$site_ids[] = $website->id;
		}
		do_action( 'mainwp_rightnow_widget_bottom', $site_ids, $globalView );
		?>

		<div class="mainwp-clear"></div>

		<div id="rightnow-upgrade-status-box" title="Upgrade" style="display: none; text-align: center">
			<div id="rightnow-upgrade-status-progress"></div>
			<span id="rightnow-upgrade-status-current">0</span> /
			<span id="rightnow-upgrade-status-total"></span> <?php _e( 'upgraded', 'mainwp' ); ?>
			<div style="height: 160px; overflow: auto; margin-top: 20px; margin-bottom: 10px; text-align: left">
				<table style="width: 100%" id="rightnow-upgrade-list">
				</table>
			</div>
			<input id="rightnow-upgrade-status-close" type="button" name="Close" value="<?php _e( 'Close', 'mainwp' ); ?>" class="button"/>
		</div>

		<div id="rightnow-backup-box" title="Full backup required" style="display: none; text-align: center">
			<div style="height: 190px; overflow: auto; margin-top: 20px; margin-bottom: 10px; text-align: left" id="rightnow-backup-content">
			</div>
			<input id="rightnow-backup-all" type="button" name="Backup All" value="<?php _e( 'Backup All', 'mainwp' ); ?>" class="button-primary"/>
			<input id="rightnow-backup-ignore" type="button" name="Ignore" value="<?php _e( 'Ignore', 'mainwp' ); ?>" class="button"/>
		</div>

		<div id="rightnow-backupnow-box" title="Full backup" style="display: none; text-align: center">
			<div style="height: 190px; overflow: auto; margin-top: 20px; margin-bottom: 10px; text-align: left" id="rightnow-backupnow-content">
			</div>
			<input id="rightnow-backupnow-close" type="button" name="Ignore" value="<?php _e( 'Cancel', 'mainwp' ); ?>" class="button"/>
		</div>

		<?php
		@MainWP_DB::free_result( $websites );
	}

	public static function renderIgnoredUpdates() {
		MainWP_Settings::renderHeader( 'IgnoredUpdates' );

		MainWP_Settings::renderFooter( 'IgnoredUpdates' );
	}


	public static function dismissSyncErrors( $dismiss = true ) {
		global $current_user;
		update_user_option( $current_user->ID, 'mainwp_syncerrors_dismissed', $dismiss );

		return true;
	}

	public static function checkBackups() {
		//if (get_option('mainwp_backup_before_upgrade') != 1) return true;
		if ( ! is_array( $_POST['sites'] ) ) {
			return true;
		}
		$global_backup_before_upgrade = get_option( 'mainwp_backup_before_upgrade' );

		$output = array();
		foreach ( $_POST['sites'] as $siteId ) {
			$website = MainWP_DB::Instance()->getWebsiteById( $siteId );
			if ( ( $website->backup_before_upgrade == 0 ) || ( ( $website->backup_before_upgrade == 2 ) && ( $global_backup_before_upgrade == 0 ) ) ) {
				continue;
			}

			$dir = MainWP_Utility::getMainWPSpecificDir( $siteId );
			//Check if backup ok
			$lastBackup = - 1;
			if ( file_exists( $dir ) && ( $dh = opendir( $dir ) ) ) {
				while ( ( $file = readdir( $dh ) ) !== false ) {
					if ( $file != '.' && $file != '..' ) {
						$theFile = $dir . $file;
						if ( MainWP_Utility::isArchive( $file ) && ! MainWP_Utility::isSQLArchive( $file ) && ( filemtime( $theFile ) > $lastBackup ) ) {
							$lastBackup = filemtime( $theFile );
						}
					}
				}
				closedir( $dh );
			}

			$output['sites'][ $siteId ] = ( $lastBackup < ( time() - ( 7 * 24 * 60 * 60 ) ) ? false : true );
		}

		return $output;
	}
}
