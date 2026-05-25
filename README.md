# NexID

Enterprise Identity & Access Management Platform.

<p align="left">
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white">
  <img alt="License" src="https://img.shields.io/badge/License-MIT-111827">
</p>

---

## Centralized Workforce Identity

NexID is a centralized Identity & Access Management (IAM) platform designed for organizations that require secure authentication, Single Sign-On (SSO), workforce identity management, and centralized authorization across multiple applications.

Built for modern enterprise environments with support for NIP-based authentication, organizational structures, role management, and secure application integration.

---

## Core Capabilities

### Identity Infrastructure
- Centralized authentication
- NIP-based workforce identity
- Multi-application Single Sign-On
- OAuth2-compatible authorization flow
- JWT token management

### Access Management
- Role-Based Access Control (RBAC)
- Permission & access profile management
- Department-based user organization
- Centralized authorization policies

### Enterprise Security
- Signed JWT validation
- Token revocation & lifecycle control
- Redirect URI validation
- Session verification & CSRF protection

---

## Platform Modules

| Module | Description |
|---|---|
| SSO Gateway | Central authentication flow |
| IAM Core | Workforce identity management |
| Access Profiles | Permission grouping & authorization |
| Application Registry | Connected application management |
| RBAC Engine | Roles & permissions |
| Token Service | JWT issuance & verification |
| Department Management | Organizational structure |

---

## Workforce Identity Architecture

```text
┌────────────────────┐
│    Client Apps     │
│────────────────────│
│ • Hospital System  │
│ • HR Platform      │
│ • Internal Apps    │
└─────────┬──────────┘
          │
          │ OAuth2 / SSO
          ▼
┌────────────────────┐
│       NexID        │
│────────────────────│
│ Identity Provider  │
│ Access Management  │
│ Token Authority    │
│ User Directory     │
└────────────────────┘
```

---

## Identity Principles

NexID is designed around workforce identity using **NIP (Nomor Induk Pegawai)** as the primary authentication identifier.

| Field | Purpose |
|---|---|
| `nip` | Primary workforce identity |
| `department_id` | Organizational mapping |
| `roles` | Access control |
| `permissions` | Authorization policies |

---

## Enterprise Use Cases

- Hospital Information Systems
- Workforce Identity Infrastructure
- Government & Institutional Platforms
- Internal Enterprise Applications
- Multi-Application Authentication Ecosystems

---

## Technology Stack

- Laravel 12
- PHP 8.2
- Filament
- Laravel Passport
- Spatie Permission
- Redis
- Vue 3
- Tailwind CSS

---

## Vision

NexID is built to become a modern workforce identity platform focused on centralized authentication, organizational access control, and scalable enterprise integration.

---

## License

MIT