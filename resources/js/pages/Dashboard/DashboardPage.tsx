import { useEffect } from 'react';
import { router, usePage, Head } from '@inertiajs/react';
import Dashboard from '../../components/Dashboard';
import { useAuth } from '../../hooks/useAuth';

export default function DashboardPage() {
  const { user, isAuthenticated, checkAuth } = useAuth();
  const page = usePage() as any;
  const inertiaAuth = (page.props.auth ?? {}) as any;
  const inertiaUser = inertiaAuth?.user;
  const applications = page.props?.applications ?? [];
  const accessProfiles = page.props?.accessProfiles ?? [];

  useEffect(() => {
    if (!inertiaUser && !isAuthenticated) {
      router.visit('/login');
    } else if (!inertiaUser && isAuthenticated) {
      checkAuth();
    }
  }, [isAuthenticated, inertiaUser, checkAuth]);

  const displayUser = inertiaUser || user;

  if ((!inertiaUser && !isAuthenticated) || !displayUser) {
    return <div>Loading...</div>;
  }

  return (
    <>
      <Head title="Dashboard" />
      <Dashboard user={displayUser} applications={applications} accessProfiles={accessProfiles} />
    </>
  );
}