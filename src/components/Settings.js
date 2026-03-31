import { useState } from '@wordpress/element';

const RATING_LABELS = {
	'1-5': '1 to 5 stars',
	'3-5': '3 to 5 stars',
	'4-5': '4 to 5 stars',
	3: '3 stars only',
	4: '4 stars only',
	5: '5 stars only',
};

const STYLE_LABELS = {
	short: 'Short blurbs',
	medium: 'Balanced paragraphs',
	long: 'Detailed paragraphs',
};

const STATUS_LABELS = {
	approved: 'Approved immediately',
	pending: 'Pending moderation',
};

const countLines = ( value ) =>
	value
		.split( '\n' )
		.map( ( item ) => item.trim() )
		.filter( Boolean ).length;

const splitIds = ( value ) =>
	value
		.split( /[\s,]+/ )
		.map( ( item ) => item.trim() )
		.filter( Boolean );

const countIds = ( value ) => splitIds( value ).length;

const normalizeIds = ( value ) =>
	Array.from(
		new Set(
			splitIds( value )
				.map( ( item ) => Number.parseInt( item, 10 ) )
				.filter( ( item ) => Number.isInteger( item ) && item > 0 )
		)
	).join( ',' );

const normalizeRange = ( startValue, endValue ) => {
	let start = Number.parseInt( startValue, 10 );
	let end = Number.parseInt( endValue, 10 );

	if ( Number.isNaN( start ) || start < 0 ) {
		start = 0;
	}

	if ( Number.isNaN( end ) || end < 0 ) {
		end = 0;
	}

	if ( start > end ) {
		[ start, end ] = [ end, start ];
	}

	return { start: String( start ), end: String( end ) };
};

const formatDateWindow = ( startValue, endValue ) => {
	const range = normalizeRange( startValue, endValue );

	if ( range.start === range.end ) {
		return `${ range.start } days ago only`;
	}

	return `${ range.start } to ${ range.end } days ago`;
};

const Settings = () => {
	const globalData = window.rhwcReactGlobal || {};
	const s = globalData.settings || {};

	const [ defaultCount, setDefaultCount ] = useState(
		String( s.defaultCount || '5' )
	);
	const [ defaultRating, setDefaultRating ] = useState(
		s.defaultRating || '4-5'
	);
	const [ defaultNames, setDefaultNames ] = useState( s.defaultNames || '' );
	const [ customComments, setCustomComments ] = useState(
		s.customComments || ''
	);
	const [ contentStyle, setContentStyle ] = useState(
		s.contentStyle || 'medium'
	);
	const [ reviewStatus, setReviewStatus ] = useState(
		s.reviewStatus || 'approved'
	);
	const [ dateFromDays, setDateFromDays ] = useState(
		String( s.dateFromDays ?? '0' )
	);
	const [ dateToDays, setDateToDays ] = useState(
		String( s.dateToDays ?? '30' )
	);
	const [ excludedProducts, setExcludedProducts ] = useState(
		s.excludedProducts || ''
	);
	const [ excludedCategories, setExcludedCategories ] = useState(
		s.excludedCategories || ''
	);
	const [ enableBulk, setEnableBulk ] = useState( s.enableBulk || 'yes' );
	const [ deleteDataOnUninstall, setDeleteDataOnUninstall ] = useState(
		s.deleteDataOnUninstall || 'no'
	);
	const [ importJson, setImportJson ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ importing, setImporting ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const [ msgType, setMsgType ] = useState( 'success' );

	const showMessage = ( type, text ) => {
		setMsgType( type );
		setMessage( text );
		setTimeout( () => setMessage( '' ), 4000 );
	};

	const applySettings = ( payload ) => {
		if ( ! payload ) {
			return;
		}

		const next = {
			defaultCount: String( payload.defaultCount ?? '5' ),
			defaultRating: payload.defaultRating || '4-5',
			defaultNames: payload.defaultNames || '',
			customComments: payload.customComments || '',
			contentStyle: payload.contentStyle || 'medium',
			reviewStatus: payload.reviewStatus || 'approved',
			dateFromDays: String( payload.dateFromDays ?? '0' ),
			dateToDays: String( payload.dateToDays ?? '30' ),
			excludedProducts: payload.excludedProducts || '',
			excludedCategories: payload.excludedCategories || '',
			enableBulk: payload.enableBulk || 'yes',
			deleteDataOnUninstall: payload.deleteDataOnUninstall || 'no',
		};

		setDefaultCount( next.defaultCount );
		setDefaultRating( next.defaultRating );
		setDefaultNames( next.defaultNames );
		setCustomComments( next.customComments );
		setContentStyle( next.contentStyle );
		setReviewStatus( next.reviewStatus );
		setDateFromDays( next.dateFromDays );
		setDateToDays( next.dateToDays );
		setExcludedProducts( next.excludedProducts );
		setExcludedCategories( next.excludedCategories );
		setEnableBulk( next.enableBulk );
		setDeleteDataOnUninstall( next.deleteDataOnUninstall );

		if ( window.rhwcReactGlobal ) {
			window.rhwcReactGlobal.settings = {
				...( window.rhwcReactGlobal.settings || {} ),
				...next,
			};
		}

		window.dispatchEvent( new Event( 'rhwc:settings-updated' ) );
	};

	const getSnapshot = () => {
		const range = normalizeRange( dateFromDays, dateToDays );

		return {
			defaultCount,
			defaultRating,
			defaultNames,
			customComments,
			contentStyle,
			reviewStatus,
			dateFromDays: range.start,
			dateToDays: range.end,
			excludedProducts: normalizeIds( excludedProducts ),
			excludedCategories: normalizeIds( excludedCategories ),
			enableBulk,
			deleteDataOnUninstall,
		};
	};

	const handleSave = async () => {
		setSaving( true );
		setMessage( '' );

		const formData = new FormData();
		formData.append( 'action', 'rhwc_save_settings' );
		formData.append( 'security', globalData.nonce );
		formData.append( 'default_count', defaultCount );
		formData.append( 'default_rating', defaultRating );
		formData.append( 'default_names', defaultNames );
		formData.append( 'custom_comments', customComments );
		formData.append( 'content_style', contentStyle );
		formData.append( 'review_status', reviewStatus );
		formData.append( 'date_from_days', dateFromDays );
		formData.append( 'date_to_days', dateToDays );
		formData.append( 'excluded_products', excludedProducts );
		formData.append( 'excluded_categories', excludedCategories );
		formData.append( 'enable_bulk', enableBulk );
		formData.append( 'delete_data_on_uninstall', deleteDataOnUninstall );

		try {
			const res = await fetch( globalData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );
			const data = await res.json();

			if ( data.success ) {
				applySettings( data.data?.settings );
				showMessage(
					'success',
					data.data?.message || 'Settings saved.'
				);
			} else {
				showMessage(
					'error',
					'Failed to save. ' + ( data.data?.message || '' )
				);
			}
		} catch ( error ) {
			showMessage( 'error', 'Server error. Please try again.' );
		}

		setSaving( false );
	};

	const handleExport = () => {
		const blob = new Blob(
			[
				JSON.stringify(
					{
						plugin: 'Review Helper for WooCommerce',
						version: globalData.version || '1.1.0',
						exportedAt: new Date().toISOString(),
						settings: getSnapshot(),
					},
					null,
					2
				),
			],
			{ type: 'application/json' }
		);
		const url = window.URL.createObjectURL( blob );
		const link = document.createElement( 'a' );

		link.href = url;
		link.download = `rhwc-settings-${ new Date()
			.toISOString()
			.slice( 0, 10 ) }.json`;
		document.body.appendChild( link );
		link.click();
		link.remove();
		window.URL.revokeObjectURL( url );

		showMessage( 'success', 'Settings exported as JSON.' );
	};

	const handleImport = async () => {
		if ( ! importJson.trim() ) {
			showMessage(
				'error',
				'Paste a settings JSON payload before importing.'
			);
			return;
		}

		setImporting( true );
		setMessage( '' );

		const formData = new FormData();
		formData.append( 'action', 'rhwc_import_settings' );
		formData.append( 'security', globalData.nonce );
		formData.append( 'settings_json', importJson );

		try {
			const res = await fetch( globalData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );
			const data = await res.json();

			if ( data.success ) {
				applySettings( data.data?.settings );
				setImportJson( '' );
				showMessage(
					'success',
					data.data?.message || 'Settings imported successfully.'
				);
			} else {
				showMessage(
					'error',
					'Import failed. ' + ( data.data?.message || '' )
				);
			}
		} catch ( error ) {
			showMessage( 'error', 'Server error. Please try again.' );
		}

		setImporting( false );
	};

	const summaryItems = [
		{
			label: 'Default batch size',
			value: `${ defaultCount } reviews`,
		},
		{
			label: 'Default rating',
			value: RATING_LABELS[ defaultRating ] || '4 to 5 stars',
		},
		{
			label: 'Review status',
			value: STATUS_LABELS[ reviewStatus ] || 'Approved immediately',
		},
		{
			label: 'Date window',
			value: formatDateWindow( dateFromDays, dateToDays ),
		},
		{
			label: 'Bulk exclusions',
			value: `${ countIds( excludedProducts ) } products / ${ countIds(
				excludedCategories
			) } categories`,
		},
		{
			label: 'Fallback style',
			value: STYLE_LABELS[ contentStyle ] || 'Balanced paragraphs',
		},
		{
			label: 'Bulk tab',
			value:
				enableBulk === 'yes'
					? 'Visible in sidebar'
					: 'Hidden from sidebar',
		},
	];

	return (
		<div className="rhwc-page-shell">
			<div className="rhwc-page-hero rhwc-page-hero--settings">
				<div className="rhwc-page-hero__main">
					<span className="rhwc-page-hero__eyebrow">
						Workspace settings
					</span>
					<h2 className="rhwc-page-hero__title">
						Defaults and safeguards
					</h2>
					<p className="rhwc-page-hero__description">
						Control the review defaults, choose approved or pending
						generation, define the date window, manage exclusions,
						and move settings between sites with JSON import and
						export.
					</p>

					<div className="rhwc-page-hero__chips">
						<span className="rhwc-page-hero__chip">{ `${ defaultCount } default reviews` }</span>
						<span className="rhwc-page-hero__chip">
							{ STATUS_LABELS[ reviewStatus ] ||
								'Approved immediately' }
						</span>
						<span className="rhwc-page-hero__chip">
							{ formatDateWindow( dateFromDays, dateToDays ) }
						</span>
						<span className="rhwc-page-hero__chip">
							{ countIds( excludedProducts ) ||
							countIds( excludedCategories )
								? 'Bulk exclusions active'
								: 'No bulk exclusions' }
						</span>
					</div>
				</div>

				<div className="rhwc-page-hero__stats">
					<div className="rhwc-page-stat">
						<span className="rhwc-page-stat__label">
							Names loaded
						</span>
						<strong className="rhwc-page-stat__value">
							{ countLines( defaultNames ) || 0 }
						</strong>
					</div>
					<div className="rhwc-page-stat">
						<span className="rhwc-page-stat__label">
							Custom comments
						</span>
						<strong className="rhwc-page-stat__value">
							{ countLines( customComments ) || 0 }
						</strong>
					</div>
					<div className="rhwc-page-stat">
						<span className="rhwc-page-stat__label">
							Excluded IDs
						</span>
						<strong className="rhwc-page-stat__value">
							{ countIds( excludedProducts ) +
								countIds( excludedCategories ) }
						</strong>
					</div>
				</div>
			</div>

			{ message && (
				<div className={ `rhwc-notice rhwc-notice--${ msgType }` }>
					{ message }
				</div>
			) }

			<div className="rhwc-page-grid rhwc-page-grid--sidebar">
				<div className="rhwc-settings-stack">
					<div className="rhwc-card rhwc-card--soft">
						<div className="rhwc-card-header">
							<div>
								<h2>Generation defaults</h2>
								<p>
									Set the starting values used whenever new
									review batches are created.
								</p>
							</div>
							<span className="rhwc-card-badge">Core setup</span>
						</div>

						<div className="rhwc-form-grid">
							<div className="rhwc-form-row">
								<label
									className="rhwc-form__label"
									htmlFor="rhwc-default-count"
								>
									Reviews per product
								</label>
								<input
									id="rhwc-default-count"
									type="number"
									className="rhwc-input"
									min="1"
									max="100"
									value={ defaultCount }
									onChange={ ( event ) =>
										setDefaultCount( event.target.value )
									}
								/>
							</div>

							<div className="rhwc-form-row">
								<label
									className="rhwc-form__label"
									htmlFor="rhwc-default-rating"
								>
									Default rating
								</label>
								<select
									id="rhwc-default-rating"
									className="rhwc-select"
									value={ defaultRating }
									onChange={ ( event ) =>
										setDefaultRating( event.target.value )
									}
								>
									<option value="5">5 stars only</option>
									<option value="4">4 stars only</option>
									<option value="3">3 stars only</option>
									<option value="4-5">4 to 5 stars</option>
									<option value="3-5">3 to 5 stars</option>
									<option value="1-5">1 to 5 stars</option>
								</select>
							</div>

							<div className="rhwc-form-row">
								<label
									className="rhwc-form__label"
									htmlFor="rhwc-content-style"
								>
									Content style
								</label>
								<select
									id="rhwc-content-style"
									className="rhwc-select"
									value={ contentStyle }
									onChange={ ( event ) =>
										setContentStyle( event.target.value )
									}
								>
									<option value="short">Short</option>
									<option value="medium">Medium</option>
									<option value="long">Long</option>
								</select>
							</div>

							<div className="rhwc-form-row">
								<label
									className="rhwc-form__label"
									htmlFor="rhwc-review-status"
								>
									Review status
								</label>
								<select
									id="rhwc-review-status"
									className="rhwc-select"
									value={ reviewStatus }
									onChange={ ( event ) =>
										setReviewStatus( event.target.value )
									}
								>
									<option value="approved">
										Approved immediately
									</option>
									<option value="pending">
										Pending moderation
									</option>
								</select>
							</div>

							<div className="rhwc-form-row">
								<label
									className="rhwc-form__label"
									htmlFor="rhwc-date-from-days"
								>
									Newest date offset
								</label>
								<input
									id="rhwc-date-from-days"
									type="number"
									className="rhwc-input"
									min="0"
									max="3650"
									value={ dateFromDays }
									onChange={ ( event ) =>
										setDateFromDays( event.target.value )
									}
								/>
							</div>

							<div className="rhwc-form-row">
								<label
									className="rhwc-form__label"
									htmlFor="rhwc-date-to-days"
								>
									Oldest date offset
								</label>
								<input
									id="rhwc-date-to-days"
									type="number"
									className="rhwc-input"
									min="0"
									max="3650"
									value={ dateToDays }
									onChange={ ( event ) =>
										setDateToDays( event.target.value )
									}
								/>
							</div>
						</div>

						<div className="rhwc-tip-box">
							Date offsets are counted from today. For example,
							<code>0</code> and <code>30</code> creates random
							review dates between today and 30 days ago.
						</div>

						<div className="rhwc-toggle-card">
							<input
								id="rhwc-enable-bulk"
								type="checkbox"
								checked={ enableBulk === 'yes' }
								onChange={ ( event ) =>
									setEnableBulk(
										event.target.checked ? 'yes' : 'no'
									)
								}
							/>
							<label
								className="rhwc-toggle-card__content"
								htmlFor="rhwc-enable-bulk"
							>
								<span className="rhwc-toggle-card__title">
									Enable Bulk Generate tab
								</span>
								<span className="rhwc-toggle-card__desc">
									Hide or show the bulk generation section in
									the sidebar navigation.
								</span>
							</label>
						</div>
					</div>

					<div className="rhwc-card rhwc-card--soft">
						<div className="rhwc-card-header">
							<div>
								<h2>Bulk exclusions</h2>
								<p>
									Skip products or whole categories during
									bulk generation without affecting single
									product generation.
								</p>
							</div>
							<span className="rhwc-card-badge">
								{ countIds( excludedProducts ) ||
								countIds( excludedCategories )
									? 'Rules active'
									: 'Optional' }
							</span>
						</div>

						<div className="rhwc-form-grid">
							<div className="rhwc-form-row">
								<label
									className="rhwc-form__label"
									htmlFor="rhwc-excluded-products"
								>
									Excluded product IDs
								</label>
								<input
									id="rhwc-excluded-products"
									type="text"
									className="rhwc-input rhwc-input--wide"
									value={ excludedProducts }
									onChange={ ( event ) =>
										setExcludedProducts(
											event.target.value
										)
									}
									placeholder="e.g. 15, 21, 44"
								/>
								<span className="rhwc-desc">
									Comma-separated product IDs that bulk
									generation should always skip.
								</span>
							</div>

							<div className="rhwc-form-row">
								<label
									className="rhwc-form__label"
									htmlFor="rhwc-excluded-categories"
								>
									Excluded category IDs
								</label>
								<input
									id="rhwc-excluded-categories"
									type="text"
									className="rhwc-input rhwc-input--wide"
									value={ excludedCategories }
									onChange={ ( event ) =>
										setExcludedCategories(
											event.target.value
										)
									}
									placeholder="e.g. 8, 12"
								/>
								<span className="rhwc-desc">
									Products inside these WooCommerce category
									IDs will also be skipped in bulk runs.
								</span>
							</div>
						</div>

						<div className="rhwc-tip-box">
							These rules apply to all-product and specific-ID
							bulk runs. Single product generation from the edit
							screen still works normally.
						</div>
					</div>

					<div className="rhwc-card rhwc-card--soft">
						<div className="rhwc-card-header">
							<div>
								<h2>Default reviewer names</h2>
								<p>
									Add one reviewer name per line to build a
									reusable pool for generated content.
								</p>
							</div>
							<span className="rhwc-card-badge">
								{ countLines( defaultNames )
									? `${ countLines( defaultNames ) } names`
									: 'Optional' }
							</span>
						</div>

						<div className="rhwc-form-row">
							<textarea
								className="rhwc-textarea rhwc-textarea--wide"
								rows="8"
								value={ defaultNames }
								onChange={ ( event ) =>
									setDefaultNames( event.target.value )
								}
								placeholder="One name per line&#10;Jane Smith&#10;Michael Chen&#10;Sara Muller"
							/>
							<div className="rhwc-tip-box">
								One reviewer name per line. Leave this empty to
								fall back to the plugin&apos;s built-in default
								name pool.
							</div>
						</div>
					</div>

					<div className="rhwc-card rhwc-card--soft">
						<div className="rhwc-card-header">
							<div>
								<h2>Custom review comments</h2>
								<p>
									Store your own fallback review lines and let
									the generator reuse them during creation.
								</p>
							</div>
							<span className="rhwc-card-badge">
								{ countLines( customComments )
									? `${ countLines(
											customComments
									  ) } comments`
									: 'Fallback content' }
							</span>
						</div>

						<div className="rhwc-form-row">
							<textarea
								className="rhwc-textarea rhwc-textarea--wide"
								rows="10"
								value={ customComments }
								onChange={ ( event ) =>
									setCustomComments( event.target.value )
								}
								placeholder="One comment per line&#10;Great product, very happy!&#10;Delivery was fast and packaging excellent."
							/>
							<div className="rhwc-tip-box">
								Use <code>{ '{product_name}' }</code> or
								<code>&lt;product&gt;</code> inside a line to
								insert the product name automatically. Leave the
								list empty to use the selected content style
								instead.
							</div>
						</div>
					</div>

					<div className="rhwc-card rhwc-card--soft">
						<div className="rhwc-card-header">
							<div>
								<h2>Import and export</h2>
								<p>
									Back up your current setup as JSON or paste
									a previously exported payload to restore it.
								</p>
							</div>
							<span className="rhwc-card-badge">
								Transfer tools
							</span>
						</div>

						<div className="rhwc-transfer-stack">
							<div className="rhwc-transfer-box">
								<strong>Export current setup</strong>
								<p>
									Download a JSON snapshot of the settings
									visible on this screen.
								</p>
								<button
									type="button"
									className="rhwc-btn rhwc-btn--secondary"
									onClick={ handleExport }
								>
									Export settings JSON
								</button>
							</div>

							<div className="rhwc-transfer-box">
								<strong>Import settings JSON</strong>
								<p>
									Paste a full exported JSON payload here to
									replace the current saved settings.
								</p>
								<textarea
									className="rhwc-textarea rhwc-textarea--wide"
									rows="8"
									value={ importJson }
									onChange={ ( event ) =>
										setImportJson( event.target.value )
									}
									placeholder='{&#10;  "plugin": "Review Helper for WooCommerce",&#10;  "settings": { ... }&#10;}'
								/>
								<div className="rhwc-transfer-actions">
									<button
										type="button"
										className="rhwc-btn rhwc-btn--primary"
										disabled={ importing }
										onClick={ handleImport }
									>
										{ importing
											? 'Importing...'
											: 'Import settings' }
									</button>
								</div>
							</div>
						</div>
					</div>

					<div className="rhwc-card rhwc-card--soft">
						<div className="rhwc-card-header">
							<div>
								<h2>Danger zone</h2>
								<p>
									These settings affect how much plugin data
									stays behind if the plugin is later removed.
								</p>
							</div>
							<span className="rhwc-card-badge rhwc-card-badge--danger">
								Use carefully
							</span>
						</div>

						<div className="rhwc-danger-zone">
							<label
								className="rhwc-danger-zone__label"
								htmlFor="rhwc-delete-data"
							>
								<input
									id="rhwc-delete-data"
									type="checkbox"
									checked={ deleteDataOnUninstall === 'yes' }
									onChange={ ( event ) =>
										setDeleteDataOnUninstall(
											event.target.checked ? 'yes' : 'no'
										)
									}
								/>
								Delete all plugin data when uninstalling
							</label>
							<p className="rhwc-danger-zone__desc">
								Settings and plugin metadata will be removed
								when uninstalling. Generated WooCommerce reviews
								remain unless you delete them manually.
							</p>
						</div>

						<div className="rhwc-action-bar">
							<div className="rhwc-action-bar__content">
								<strong>Save changes</strong>
								<span>
									Your updated defaults will be used by the
									React dashboard right after saving.
								</span>
							</div>

							<button
								type="button"
								className="rhwc-btn rhwc-btn--primary"
								disabled={ saving || importing }
								onClick={ handleSave }
							>
								{ saving ? (
									<>
										<span className="rhwc-inline-spinner" />
										Saving...
									</>
								) : (
									<>
										<svg
											width="15"
											height="15"
											fill="none"
											viewBox="0 0 24 24"
											stroke="currentColor"
											strokeWidth={ 2.5 }
										>
											<path
												strokeLinecap="round"
												strokeLinejoin="round"
												d="M5 13l4 4L19 7"
											/>
										</svg>
										Save settings
									</>
								) }
							</button>
						</div>
					</div>
				</div>

				<aside className="rhwc-card rhwc-card--soft">
					<div className="rhwc-card-header">
						<div>
							<h2>Current setup</h2>
							<p>
								A compact summary of the defaults that will be
								used across the dashboard.
							</p>
						</div>
					</div>

					<div className="rhwc-info-list">
						{ summaryItems.map( ( item ) => (
							<div
								key={ item.label }
								className="rhwc-info-list__item"
							>
								<span className="rhwc-info-list__label">
									{ item.label }
								</span>
								<strong className="rhwc-info-list__value">
									{ item.value }
								</strong>
							</div>
						) ) }
					</div>

					<div className="rhwc-note-card">
						<strong>Suggestion</strong>
						<p>
							Keep a small but varied custom comment list so
							generated reviews feel more natural across different
							products.
						</p>
					</div>
				</aside>
			</div>
		</div>
	);
};

export default Settings;
