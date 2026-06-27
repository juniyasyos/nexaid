# Nexaid v1.0.0 - First Release 🎉

We are thrilled to announce the first official release (v1.0.0) of **Nexaid** — an Enterprise Identity and Access Management (IAM) platform providing Single Sign-On (SSO), centralized authentication, role-based access control (RBAC), and application federation.

## 🌟 Key Features
- **Centralized Authentication & SSO:** Seamless Single Sign-On flow with secure verification, authorization code handling, and robust session management.
- **Dynamic Application Federation:** Easily manage third-party applications with client role fetching logic and toggleable application states (push and user role services skip disabled apps).
- **Role-Based Access Control (RBAC):** Comprehensive access profile management, allowing precise mapping between roles and users across client applications.
- **Dynamic UI & Dashboard:** Redesigned dashboard and profile settings layout, complete with multiple dynamic login view selections, branding options, and new animations.
- **Media & Avatar Support:** Integrated AWS S3 / MinIO support for dynamic avatars and signatures.
- **System Administration:** Added capabilities to synchronize user and role configurations directly via robust push-only synchronization services.

## 🚀 Enhancements
- **Security & Authorization:** Enhanced IAM role seeders, improved SSO flow, and reinforced application access with strict permission policies for panel admins.
- **UI/UX Updates:** Refined application forms, enhanced access summaries, updated icons, and corrected labels for a polished user experience.
- **Configuration Optimization:** Streamlined SSO and IAM secrets management by migrating away from database settings in favor of environment-based `config()` strategies.
- **Documentation:** Added comprehensive Login Flow documentation, sequence diagrams, and third-party license declarations (shifted to proprietary).
- **Data Normalization:** Added seeders to normalize Indonesian gender values to English for structural consistency.

## 🐛 Bug Fixes
- Fixed an issue causing unintended SSO auto-logout and missing session models.
- Resolved critical logic flaws in the SSO verification flow.
- Allowed IAM admins to accurately retrieve their assigned roles within client applications.
- Fixed S3 login view image logic for consistent rendering across local and production environments.
- Corrected application toggles and profile access roles logic.

---
**Full Changelog:** [https://github.com/juniyasyos/nexaid/commits/v1.0.0](https://github.com/juniyasyos/nexaid/commits/v1.0.0)
