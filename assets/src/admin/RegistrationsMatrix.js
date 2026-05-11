import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';

export default function RegistrationsMatrix( {
	availableTypes,
	registrations,
	programmaticRegistrations,
	onChange,
} ) {
	function isPairIn( list, postType, taxonomy ) {
		return list.some(
			( r ) => r.postType === postType && r.taxonomy === taxonomy
		);
	}

	function handleToggle( postType, taxonomy, checked ) {
		if ( checked ) {
			onChange( [ ...registrations, { postType, taxonomy } ] );
		} else {
			onChange(
				registrations.filter(
					( r ) =>
						! ( r.postType === postType && r.taxonomy === taxonomy )
				)
			);
		}
	}

	if ( ! availableTypes.length ) {
		return (
			<p>
				{ __(
					'No post types with UI-visible taxonomies found.',
					'whaze-term-order-for-posts'
				) }
			</p>
		);
	}

	return (
		<>
			{ availableTypes.map(
				( { postType, postTypeLabel, taxonomies } ) => (
					<div key={ postType } style={ { marginBottom: '16px' } }>
						<h3 style={ { marginBottom: '8px' } }>
							{ postTypeLabel }
						</h3>
						{ taxonomies.map( ( { taxonomy, label } ) => {
							const isProgrammatic = isPairIn(
								programmaticRegistrations,
								postType,
								taxonomy
							);
							const isChecked =
								isProgrammatic ||
								isPairIn( registrations, postType, taxonomy );

							return (
								<CheckboxControl
									key={ taxonomy }
									label={
										isProgrammatic
											? /* translators: %s: taxonomy label */
											  label +
											  ' — ' +
											  __(
													'Registered via code',
													'whaze-term-order-for-posts'
											  )
											: label
									}
									checked={ isChecked }
									disabled={ isProgrammatic }
									onChange={ ( checked ) =>
										handleToggle(
											postType,
											taxonomy,
											checked
										)
									}
								/>
							);
						} ) }
					</div>
				)
			) }
		</>
	);
}
