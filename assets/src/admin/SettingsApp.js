import { useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Notice,
	Panel,
	PanelBody,
} from '@wordpress/components';
import RegistrationsMatrix from './RegistrationsMatrix';

const OPTION_KEY = 'whaze_term_order_for_posts_registrations';
const AUTO_APPLY_KEY = 'whaze_term_order_for_posts_auto_apply';

export default function SettingsApp() {
	const {
		availableTypes,
		savedRegistrations,
		programmaticRegistrations,
		autoApply: savedAutoApply,
	} = window.whazeTermOrderForPostsAdmin;

	const [ registrations, setRegistrations ] = useState( savedRegistrations );
	const [ autoApply, setAutoApply ] = useState( savedAutoApply );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const handleSave = useCallback( async () => {
		setIsSaving( true );
		setNotice( null );

		try {
			await apiFetch( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: {
					[ OPTION_KEY ]: registrations,
					[ AUTO_APPLY_KEY ]: autoApply,
				},
			} );
			setNotice( {
				type: 'success',
				message: __( 'Settings saved.', 'whaze-term-order-for-posts' ),
			} );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: sprintf(
					/* translators: %s: error message */
					__(
						'Could not save settings: %s',
						'whaze-term-order-for-posts'
					),
					error?.message ??
						__( 'Unknown error.', 'whaze-term-order-for-posts' )
				),
			} );
		} finally {
			setIsSaving( false );
		}
	}, [ registrations, autoApply ] );

	return (
		<Panel
			header={ __(
				'Term Order for Posts — Settings',
				'whaze-term-order-for-posts'
			) }
		>
			{ notice && (
				<div style={ { padding: '0 16px' } }>
					<Notice
						status={ notice.type }
						isDismissible
						onRemove={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				</div>
			) }
			<PanelBody
				title={ __(
					'Post type / taxonomy pairs',
					'whaze-term-order-for-posts'
				) }
			>
				<p>
					{ __(
						'Enable term ordering for each post type and taxonomy combination. Pairs registered via code are shown as read-only.',
						'whaze-term-order-for-posts'
					) }
				</p>
				<RegistrationsMatrix
					availableTypes={ availableTypes }
					registrations={ registrations }
					programmaticRegistrations={ programmaticRegistrations }
					onChange={ setRegistrations }
				/>
			</PanelBody>
			<PanelBody
				title={ __(
					'Frontend rendering',
					'whaze-term-order-for-posts'
				) }
			>
				<CheckboxControl
					label={ __(
						'Automatically apply custom order (native blocks, themes, REST API)',
						'whaze-term-order-for-posts'
					) }
					help={ __(
						'Hooks into get_the_terms(). Applies to all registered pairs. No code required.',
						'whaze-term-order-for-posts'
					) }
					checked={ autoApply }
					onChange={ setAutoApply }
				/>
			</PanelBody>
			<PanelBody>
				<Button
					variant="primary"
					isBusy={ isSaving }
					disabled={ isSaving }
					onClick={ handleSave }
				>
					{ __( 'Save Settings', 'whaze-term-order-for-posts' ) }
				</Button>
			</PanelBody>
		</Panel>
	);
}
