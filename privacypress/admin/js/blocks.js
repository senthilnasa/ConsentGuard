/**
 * PrivacyPress Gutenberg blocks (editor side; rendering is server-side).
 *
 * @package PCM
 */
( function ( blocks, element, i18n ) {
	'use strict';

	var elCreate = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'privacypress/privacy-settings', {
		title: __( 'Privacy Settings Button', 'privacypress' ),
		description: __( 'A button that opens the PrivacyPress consent preferences modal.', 'privacypress' ),
		icon: 'privacy',
		category: 'widgets',
		attributes: {
			label: { type: 'string', default: '' }
		},
		edit: function ( props ) {
			return elCreate(
				'div',
				{ style: { padding: '8px' } },
				elCreate( 'button', {
					type: 'button',
					className: 'components-button is-secondary',
					onClick: function ( e ) {
						e.preventDefault();
					}
				}, props.attributes.label || __( 'Privacy Settings', 'privacypress' ) ),
				elCreate( 'input', {
					type: 'text',
					placeholder: __( 'Custom label (optional)', 'privacypress' ),
					value: props.attributes.label,
					style: { display: 'block', marginTop: '8px', width: '100%' },
					onChange: function ( e ) {
						props.setAttributes( { label: e.target.value } );
					}
				} )
			);
		},
		save: function () {
			return null; // Dynamic block, rendered server-side.
		}
	} );

	blocks.registerBlockType( 'privacypress/cookie-table', {
		title: __( 'Cookie Details Table', 'privacypress' ),
		description: __( 'Renders the live PrivacyPress cookie inventory, always in sync with your configuration. Ideal for the Cookie Policy page.', 'privacypress' ),
		icon: 'editor-table',
		category: 'widgets',
		edit: function () {
			return elCreate(
				'div',
				{ style: { padding: '12px', border: '1px dashed #999', borderRadius: '4px' } },
				__( 'Cookie Details Table — the configured cookie inventory renders here, grouped by consent category.', 'privacypress' )
			);
		},
		save: function () {
			return null; // Dynamic block, rendered server-side.
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.i18n ) );
