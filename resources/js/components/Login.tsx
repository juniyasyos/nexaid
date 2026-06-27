import React, { useState, useEffect } from 'react';
import axios from 'axios';

import LoginViewType1 from './Login/LoginViewType1';
import LoginViewType2 from './Login/LoginViewType2';
import LoginDefaultView from './Login/LoginDefaultView';

const LoginLoader = () => (
  <div className="min-h-screen flex items-center justify-center p-4">
    <div className="animate-spin rounded-full h-10 w-10 border-4 border-gray-200 border-t-blue-600"></div>
  </div>
);

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

    const loadConfig = async () => {
      try {
        const r = await axios.get('/api/settings/login-config');
        if (isMounted) {
          setCompanyName(r.data.company_name ?? '');
          const v = r.data.login_view ?? 'type1';
          setViewType(v === 'default' ? 'default' : v === 'type2' ? 'type2' : 'type1');
        }
      } catch (err) {
        if (isMounted) {
          setViewType('type1');
        }
      }
    };

    loadConfig();

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

  if (!viewType) return <LoginLoader />;

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
    <>
      {viewType === 'default' ? (
        <LoginDefaultView {...commonProps} />
      ) : viewType === 'type1' ? (
        <LoginViewType1 {...commonProps} />
      ) : viewType === 'type2' ? (
        <LoginViewType2 {...commonProps} />
      ) : null}
    </>
  );
}