import React, { useState, useEffect, lazy, Suspense } from 'react';
import axios from 'axios';

const LoginViewType1 = lazy(() => import('./Login/LoginViewType1'));
const LoginViewType2 = lazy(() => import('./Login/LoginViewType2'));
const LoginDefaultView = lazy(() => import('./Login/LoginDefaultView'));

interface CompanyData {
  name?: string;
}

interface LoginProps {
  onLogin: (nip: string, password: string) => void;
  isLoading?: boolean;
  error?: string | null;
  devAutofill?: {
    nip?: string;
    password?: string;
  } | null;
}

export default function Login({ onLogin, isLoading = false, error, devAutofill = null }: LoginProps) {
  const [nip, setNip] = useState('');
  const [password, setPassword] = useState('');
  const [focusedInput, setFocusedInput] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);
  const [showError, setShowError] = useState(false);
  const [companyName, setCompanyName] = useState<string>('');
  const [viewType, setViewType] = useState<'type1' | 'type2' | 'default' | null>(null);

  // Auto-fill untuk development mode
  useEffect(() => {
    if (devAutofill?.nip && devAutofill?.password) {
      setNip(devAutofill.nip);
      setPassword(devAutofill.password);
      return;
    }

    const isDev = import.meta.env.VITE_APP_ENV === 'dev';
    if (isDev) {
      const devNip = import.meta.env.VITE_DEV_NIP || '';
      const devPassword = import.meta.env.VITE_DEV_PASSWORD || '';
      setNip(devNip);
      setPassword(devPassword);
    }
  }, [devAutofill]);

  useEffect(() => {
    let isMounted = true;

    const loadCompanyData = async () => {
      try {
        const response = await axios.get<CompanyData>('/api/company');
        if (isMounted) {
          setCompanyName(response.data.name ?? '');
        }
      } catch (err) {
        console.warn('Unable to load company data', err);
      }
    };

    loadCompanyData();

    // load login view preference (lightweight)
    const loadViewPref = async () => {
      try {
        const r = await axios.get('/api/settings/login-view');
        if (isMounted) {
          const v = r.data?.value ?? 'type1';
          setViewType(v === 'default' ? 'default' : v === 'type2' ? 'type2' : 'type1');
        }
      } catch (err) {
        if (isMounted) setViewType('type1');
      }
    };

    loadViewPref();

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    if (error) {
      setShowError(true);

      const timer = setTimeout(() => {
        setShowError(false);
      }, 4000);

      return () => clearTimeout(timer);
    }
  }, [error]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (nip && password && !isLoading) {
      onLogin(nip, password);
    }
  };

  if (!viewType) return null; // still loading preference

  const commonProps = {
    nip,
    setNip,
    password,
    setPassword,
    focusedInput,
    setFocusedInput,
    showPassword,
    setShowPassword,
    showError,
    onCloseError: () => setShowError(false),
    handleSubmit,
    isLoading: Boolean(isLoading),
    error,
    companyName,
  } as const;

  return (
    <Suspense fallback={null}>
      {viewType === 'default' ? (
        <LoginDefaultView {...commonProps} />
      ) : viewType === 'type1' ? (
        <LoginViewType1 {...commonProps} />
      ) : viewType === 'type2' ? (
        <LoginViewType2 {...commonProps} />
      ) : null}
    </Suspense>
  );
}