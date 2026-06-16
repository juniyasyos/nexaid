// Barrel export untuk Dashboard components
export { default as Dashboard } from './Dashboard';
export { default as ModalContent } from './ModalContent';
export { default as ApplicationCard } from './ApplicationCard';
export { useApplications } from './useApplications';

// Exports types
export type {
    DashboardProps,
    ApplicationWithIcon,
    ModalContentProps,
    AccessProfile,
    ApplicationData,
    ApplicationRole,
} from './types';

// Exports constants
export {
    APP_CONFIG,
    DEFAULT_APP_CONFIG,
    DASHBOARD_TEXTS,
    MODAL_TEXTS,
    ANIMATION_VARIANTS,
} from './constants';

// Exports styles
export { KEYFRAME_STYLES } from './styles';
