const formatContentStyle = ( value ) => {
	const styleMap = {
		short: 'Short blurbs',
		medium: 'Balanced paragraphs',
		long: 'Detailed paragraphs',
	};

	return styleMap[ value ] || styleMap.medium;
};

const formatRatingLabel = ( value ) => {
	const ratingMap = {
		'1-5': '1 to 5 stars',
		'3-5': '3 to 5 stars',
		'4-5': '4 to 5 stars',
		3: '3 stars only',
		4: '4 stars only',
		5: '5 stars only',
	};

	return ratingMap[ value ] || '4 to 5 stars';
};

const Dashboard = ( { setActiveTab, globalData } ) => {
	const stats = globalData.stats || {};
	const settings = globalData.settings || {};

	const totalGenerated = stats.totalGenerated || 0;
	const lastGeneratedDate = stats.lastGeneratedDate || 'Never';
	const bulkEnabled = settings.enableBulk !== 'no';
	const defaultCount = settings.defaultCount || 5;
	const defaultRating = settings.defaultRating || '4-5';
	const customCommentsEnabled = Boolean( settings.customComments?.trim() );
	const customNamesEnabled = Boolean( settings.defaultNames?.trim() );
	const contentStyle = formatContentStyle( settings.contentStyle );

	const quickActions = [
		bulkEnabled && {
			key: 'bulk',
			title: 'Bulk Generate',
			subtitle:
				'Create review batches for multiple products from one clean workflow.',
			tag: 'Fastest',
			emphasis: true,
			icon: (
				<svg
					width="18"
					height="18"
					fill="none"
					viewBox="0 0 24 24"
					stroke="currentColor"
					strokeWidth={ 2 }
				>
					<path
						strokeLinecap="round"
						strokeLinejoin="round"
						d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
					/>
				</svg>
			),
		},
		{
			key: 'manage',
			title: 'Manage Reviews',
			subtitle:
				'Approve, edit, or remove sample reviews directly from the review table.',
			tag: 'Review',
			icon: (
				<svg
					width="18"
					height="18"
					fill="none"
					viewBox="0 0 24 24"
					stroke="currentColor"
					strokeWidth={ 2 }
				>
					<path
						strokeLinecap="round"
						strokeLinejoin="round"
						d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
					/>
				</svg>
			),
		},
		{
			key: 'settings',
			title: 'Settings',
			subtitle:
				'Control defaults, reviewer names, custom comments, and plugin safeguards.',
			tag: 'Setup',
			icon: (
				<svg
					width="18"
					height="18"
					fill="none"
					viewBox="0 0 24 24"
					stroke="currentColor"
					strokeWidth={ 2 }
				>
					<path
						strokeLinecap="round"
						strokeLinejoin="round"
						d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
					/>
					<path
						strokeLinecap="round"
						strokeLinejoin="round"
						d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
					/>
				</svg>
			),
		},
	].filter( Boolean );

	const overviewItems = [
		{
			label: 'Fallback content',
			value: customCommentsEnabled
				? 'Custom comment library'
				: contentStyle,
		},
		{
			label: 'Default reviewer names',
			value: customNamesEnabled
				? 'Custom list added'
				: 'Using built-in names',
		},
		{
			label: 'Bulk generation tab',
			value: bulkEnabled ? 'Visible in sidebar' : 'Hidden from sidebar',
		},
		{
			label: 'Default rating',
			value: formatRatingLabel( defaultRating ),
		},
	];

	const highlightCards = [
		{
			label: 'Total generated',
			value: totalGenerated,
			meta: 'Reviews created in this store',
		},
		{
			label: 'Batch default',
			value: `${ defaultCount } per product`,
			meta: 'Current generation volume',
		},
		{
			label: 'Content source',
			value: customCommentsEnabled ? 'Custom comments' : contentStyle,
			meta: 'Fallback text mode',
		},
	];

	return (
		<div className="rhwc-page-shell">
			<div className="rhwc-page-hero rhwc-page-hero--dashboard">
				<div className="rhwc-page-hero__main">
					<span className="rhwc-page-hero__eyebrow">
						WooCommerce dashboard
					</span>
					<h2 className="rhwc-page-hero__title">
						Review Helper workspace
					</h2>
					<p className="rhwc-page-hero__description">
						Monitor review activity, jump into the right workflow
						quickly, and keep your saved generation defaults visible
						from one place.
					</p>

					<div className="rhwc-page-hero__chips">
						<span className="rhwc-page-hero__chip">{ `${ totalGenerated } total reviews` }</span>
						<span className="rhwc-page-hero__chip">{ `${ defaultCount } reviews per product` }</span>
						<span className="rhwc-page-hero__chip">
							{ bulkEnabled
								? 'Bulk tools enabled'
								: 'Bulk tools hidden' }
						</span>
					</div>
				</div>

				<div className="rhwc-page-hero__stats">
					<div className="rhwc-page-stat">
						<span className="rhwc-page-stat__label">
							Last generated
						</span>
						<strong className="rhwc-page-stat__value">
							{ lastGeneratedDate }
						</strong>
					</div>
					<div className="rhwc-page-stat">
						<span className="rhwc-page-stat__label">
							Default rating
						</span>
						<strong className="rhwc-page-stat__value">
							{ formatRatingLabel( defaultRating ) }
						</strong>
					</div>
				</div>
			</div>

			<div className="rhwc-dashboard-highlights">
				{ highlightCards.map( ( card ) => (
					<div
						key={ card.label }
						className="rhwc-dashboard-highlight"
					>
						<span className="rhwc-dashboard-highlight__label">
							{ card.label }
						</span>
						<strong className="rhwc-dashboard-highlight__value">
							{ card.value }
						</strong>
						<span className="rhwc-dashboard-highlight__meta">
							{ card.meta }
						</span>
					</div>
				) ) }
			</div>

			<div className="rhwc-page-grid rhwc-page-grid--sidebar">
				<div className="rhwc-dashboard-stack">
					<div className="rhwc-card rhwc-card--soft">
						<div className="rhwc-card-header">
							<div>
								<h2>Quick actions</h2>
								<p>
									Open the most-used sections instantly
									without searching through the sidebar.
								</p>
							</div>
							<span className="rhwc-card-badge">
								Daily workflow
							</span>
						</div>

						<div className="rhwc-actions-grid">
							{ quickActions.map( ( action ) => (
								<button
									key={ action.key }
									type="button"
									className={ `rhwc-action-btn ${
										action.emphasis
											? 'rhwc-action-btn--primary'
											: ''
									}` }
									onClick={ () => setActiveTab( action.key ) }
								>
									<div className="rhwc-action-btn__icon">
										{ action.icon }
									</div>

									<div className="rhwc-action-btn__content">
										<div className="rhwc-action-btn__topline">
											<span className="rhwc-action-btn__title">
												{ action.title }
											</span>
											<span className="rhwc-action-btn__tag">
												{ action.tag }
											</span>
										</div>
										<span className="rhwc-action-btn__sub">
											{ action.subtitle }
										</span>
									</div>

									<span className="rhwc-action-btn__arrow">
										<svg
											width="16"
											height="16"
											fill="none"
											viewBox="0 0 24 24"
											stroke="currentColor"
											strokeWidth={ 2.4 }
										>
											<path
												strokeLinecap="round"
												strokeLinejoin="round"
												d="M9 5l7 7-7 7"
											/>
										</svg>
									</span>
								</button>
							) ) }
						</div>
					</div>

					<div className="rhwc-card rhwc-card--soft">
						<div className="rhwc-card-header">
							<div>
								<h2>Workspace highlights</h2>
								<p>
									A quick explanation of how the generator is
									currently set up and what to review next.
								</p>
							</div>
							<span className="rhwc-card-badge">Overview</span>
						</div>

						<div className="rhwc-dashboard-note-grid">
							<div className="rhwc-note-card">
								<strong>Generator status</strong>
								<p>
									{ bulkEnabled
										? 'Bulk generation is available from the sidebar, so you can create review batches whenever needed.'
										: 'Bulk generation is currently hidden. You can re-enable it from Settings whenever you want.' }
								</p>
							</div>

							<div className="rhwc-note-card">
								<strong>Content mode</strong>
								<p>
									{ customCommentsEnabled
										? 'Your custom comment library is active, so generated reviews will draw from saved comment lines first.'
										: `Fallback text is currently set to ${ contentStyle.toLowerCase() }, which will be used when no custom comments are saved.` }
								</p>
							</div>
						</div>
					</div>
				</div>

				<aside className="rhwc-card rhwc-card--soft">
					<div className="rhwc-card-header">
						<div>
							<h2>Current setup</h2>
							<p>
								Your saved defaults at a glance, so it is easy
								to spot what is configured right now.
							</p>
						</div>
					</div>

					<div className="rhwc-info-list">
						{ overviewItems.map( ( item ) => (
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
						<strong>Next best step</strong>
						<p>
							{ totalGenerated > 0
								? 'Open Manage Reviews to review approvals, clean up text, and keep your generated review list tidy.'
								: 'Start with Bulk Generate to create the first set of sample reviews for your products.' }
						</p>
					</div>
				</aside>
			</div>
		</div>
	);
};

export default Dashboard;
