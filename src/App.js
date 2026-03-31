import { useEffect, useState } from '@wordpress/element';
import Dashboard from './components/Dashboard';
import BulkGenerate from './components/BulkGenerate';
import ManageReviews from './components/ManageReviews';
import Settings from './components/Settings';

const NAV_ITEMS = [
	{
		id: 'dashboard',
		label: 'Dashboard',
		description: 'Overview and shortcuts',
		icon: (
			<svg
				width="20"
				height="20"
				fill="none"
				viewBox="0 0 24 24"
				stroke="currentColor"
				strokeWidth={ 2 }
			>
				<path
					strokeLinecap="round"
					strokeLinejoin="round"
					d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
				/>
			</svg>
		),
	},
	{
		id: 'bulk',
		label: 'Bulk Generate',
		description: 'Create reviews in batches',
		icon: (
			<svg
				width="20"
				height="20"
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
		requireBulk: true,
	},
	{
		id: 'manage',
		label: 'Manage Reviews',
		description: 'Approve, edit, and clean up',
		icon: (
			<svg
				width="20"
				height="20"
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
		id: 'settings',
		label: 'Settings',
		description: 'Defaults and safeguards',
		icon: (
			<svg
				width="20"
				height="20"
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
];

const PAGE_TITLES = {
	dashboard: 'Dashboard',
	bulk: 'Bulk Generate',
	manage: 'Manage Reviews',
	settings: 'Settings',
};

const PAGE_DESCRIPTIONS = {
	dashboard: 'See your review workspace, shortcuts, and saved defaults.',
	bulk: 'Generate review batches for many products from one screen.',
	manage: 'Review, approve, edit, or remove generated comments.',
	settings: 'Control generation defaults, names, and plugin behavior.',
};

const App = () => {
	const globalData = window.rhwcReactGlobal || { settings: {} };
	const [ activeTab, setActiveTab ] = useState( 'dashboard' );
	const [ sidebarCollapsed, setSidebarCollapsed ] = useState( false );
	const [ , setSettingsRevision ] = useState( 0 );

	useEffect( () => {
		const handleSettingsUpdate = () => {
			setSettingsRevision( ( value ) => value + 1 );
		};

		window.addEventListener(
			'rhwc:settings-updated',
			handleSettingsUpdate
		);

		return () => {
			window.removeEventListener(
				'rhwc:settings-updated',
				handleSettingsUpdate
			);
		};
	}, [] );

	const enableBulk = globalData.settings?.enableBulk !== 'no';
	const totalGenerated = globalData.stats?.totalGenerated || 0;

	const visibleNavItems = NAV_ITEMS.filter(
		( item ) => ! item.requireBulk || enableBulk
	);

	const renderContent = () => {
		switch ( activeTab ) {
			case 'bulk':
				return <BulkGenerate />;
			case 'manage':
				return <ManageReviews />;
			case 'settings':
				return <Settings />;
			default:
				return (
					<Dashboard
						setActiveTab={ setActiveTab }
						globalData={ globalData }
					/>
				);
		}
	};

	return (
		<div
			className={ `rhwc-app ${
				sidebarCollapsed ? 'rhwc-app--collapsed' : ''
			}` }
		>
			<aside className="rhwc-sidebar">
				<div className="rhwc-sidebar__header">
					<div className="rhwc-sidebar__logo">
						<div className="rhwc-sidebar__logo-icon">
							<svg
								width="22"
								height="22"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								strokeWidth="2"
							>
								<path
									strokeLinecap="round"
									strokeLinejoin="round"
									d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
								/>
							</svg>
						</div>

						{ ! sidebarCollapsed && (
							<div className="rhwc-sidebar__logo-text">
								<strong>Review Helper</strong>
								<span>for WooCommerce</span>
							</div>
						) }
					</div>

					<button
						type="button"
						className="rhwc-sidebar__toggle"
						onClick={ () =>
							setSidebarCollapsed( ! sidebarCollapsed )
						}
						aria-label={
							sidebarCollapsed
								? 'Expand sidebar'
								: 'Collapse sidebar'
						}
						title={
							sidebarCollapsed ? 'Expand menu' : 'Collapse menu'
						}
					>
						<svg
							width="16"
							height="16"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="2.5"
						>
							{ sidebarCollapsed ? (
								<path
									strokeLinecap="round"
									strokeLinejoin="round"
									d="M9 5l7 7-7 7"
								/>
							) : (
								<path
									strokeLinecap="round"
									strokeLinejoin="round"
									d="M15 19l-7-7 7-7"
								/>
							) }
						</svg>
					</button>
				</div>

				{ ! sidebarCollapsed && (
					<div className="rhwc-sidebar__section-label">Workspace</div>
				) }

				<nav className="rhwc-sidebar__nav">
					{ visibleNavItems.map( ( item ) => (
						<button
							key={ item.id }
							type="button"
							className={ `rhwc-nav-item ${
								activeTab === item.id
									? 'rhwc-nav-item--active'
									: ''
							}` }
							onClick={ () => setActiveTab( item.id ) }
							title={ sidebarCollapsed ? item.label : '' }
						>
							<span className="rhwc-nav-item__icon-wrap">
								<span className="rhwc-nav-item__icon">
									{ item.icon }
								</span>
							</span>

							{ ! sidebarCollapsed && (
								<>
									<span className="rhwc-nav-item__content">
										<span className="rhwc-nav-item__label">
											{ item.label }
										</span>
										<span className="rhwc-nav-item__meta">
											{ item.description }
										</span>
									</span>

									<span
										className="rhwc-nav-item__chevron"
										aria-hidden="true"
									>
										<svg
											width="14"
											height="14"
											viewBox="0 0 24 24"
											fill="none"
											stroke="currentColor"
											strokeWidth="2.4"
										>
											<path
												strokeLinecap="round"
												strokeLinejoin="round"
												d="M9 5l7 7-7 7"
											/>
										</svg>
									</span>
								</>
							) }

							{ activeTab === item.id && (
								<span className="rhwc-nav-item__indicator" />
							) }
						</button>
					) ) }
				</nav>

				{ ! sidebarCollapsed && (
					<div className="rhwc-sidebar__footer">
						<div className="rhwc-sidebar__footer-card">
							<span className="rhwc-sidebar__footer-label">
								Plugin status
							</span>
							<strong>Ready to generate</strong>
							<div className="rhwc-sidebar__footer-meta">
								<span>
									{ enableBulk
										? 'Bulk enabled'
										: 'Bulk hidden' }
								</span>
								<span>{ `${ totalGenerated } total reviews` }</span>
							</div>
						</div>

						<span className="rhwc-version-badge">
							{ `v${ globalData.version || '1.1.0' }` }
						</span>
					</div>
				) }
			</aside>

			<main className="rhwc-main">
				<div className="rhwc-topbar">
					<div className="rhwc-topbar__heading">
						<div className="rhwc-topbar__breadcrumb">
							<span className="rhwc-topbar__plugin">
								Review Helper
							</span>
							<svg
								width="14"
								height="14"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								strokeWidth="2"
								style={ { opacity: 0.4 } }
							>
								<path
									strokeLinecap="round"
									strokeLinejoin="round"
									d="M9 5l7 7-7 7"
								/>
							</svg>
							<span className="rhwc-topbar__page">
								{ PAGE_TITLES[ activeTab ] }
							</span>
						</div>
						<p className="rhwc-topbar__description">
							{ PAGE_DESCRIPTIONS[ activeTab ] }
						</p>
					</div>

					<div className="rhwc-topbar__status">
						<span className="rhwc-topbar__status-dot" />
						WooCommerce admin
					</div>
				</div>

				<div className="rhwc-content" key={ activeTab }>
					{ renderContent() }
				</div>
			</main>
		</div>
	);
};

export default App;
