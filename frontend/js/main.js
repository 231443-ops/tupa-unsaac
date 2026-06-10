/**
 * Main.js - Utilidades globales
 * TUPA UNSAAC
 */

const App = (() => {
    // Contenedor de toasts
    let toastContainer = null;

    /**
     * Inicializar la aplicación
     */
    const init = () => {
        // Crear contenedor de toasts si no existe
        if (!document.getElementById('toast-container')) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        } else {
            toastContainer = document.getElementById('toast-container');
        }

        // Configurar eventos globales
        setupGlobalEvents();
    };

    /**
     * Configurar eventos globales
     */
    const setupGlobalEvents = () => {
        // Cerrar modales con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    closeModal(activeModal.id);
                }
            }
        });

        // Toggle sidebar en móvil
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.toggle('active');
                }
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        }
    };

    /**
     * Verificar si el usuario está autenticado
     * Redirige a login.html si no hay sesión
     * @returns {Object|null} Datos del usuario o null
     */
    const checkAuth = () => {
        const session = sessionStorage.getItem('tupa_session');
        const user = sessionStorage.getItem('tupa_user');

        if (!session || !user) {
            // Guardar URL actual para redirección después del login
            const currentPage = window.location.pathname.split('/').pop();
            if (currentPage !== 'login.html' && currentPage !== 'register.html') {
                sessionStorage.setItem('tupa_redirect', window.location.href);
            }
            window.location.href = 'login.html';
            return null;
        }

        try {
            return JSON.parse(user);
        } catch (e) {
            console.error('Error parsing user data:', e);
            sessionStorage.removeItem('tupa_session');
            sessionStorage.removeItem('tupa_user');
            window.location.href = 'login.html';
            return null;
        }
    };

    /**
     * Obtener datos del usuario actual
     * @returns {Object|null}
     */
    const getCurrentUser = () => {
        const user = sessionStorage.getItem('tupa_user');
        if (!user) return null;

        try {
            return JSON.parse(user);
        } catch (e) {
            return null;
        }
    };

    /**
     * Determinar el tipo de usuario a partir de la sesión.
     * El sistema lo deduce solo (a diferencia de Pladdes, que lo pregunta cada vez):
     * - rol admin            -> administrador del sistema
     * - rol jefe/operador    -> personal administrativo
     * - email 6 dígitos @unsaac.edu.pe -> estudiante
     * - otro email @unsaac.edu.pe      -> docente
     * - cualquier otro email           -> público general
     * @param {Object} user - Datos del usuario (de sessionStorage)
     * @returns {{tipo: string, label: string, publico: string}}
     *   tipo: estudiante|docente|administrativo|publico|admin
     *   label: sufijo para el mensaje de bienvenida ('' para público)
     *   publico: valor del filtro para tupa/procedimientos.php
     */
    const getUserTipo = (user) => {
        if (!user) return { tipo: 'publico', label: '', publico: 'todos' };

        if (user.rol === 'admin') {
            return { tipo: 'admin', label: 'Administrador del sistema', publico: '' };
        }
        if (user.rol === 'jefe' || user.rol === 'operador') {
            return { tipo: 'administrativo', label: 'Personal Administrativo', publico: 'docentes' };
        }

        const email = (user.email || '').toLowerCase();
        if (/^\d{6}@unsaac\.edu\.pe$/.test(email)) {
            return { tipo: 'estudiante', label: 'Estudiante UNSAAC', publico: 'estudiantes' };
        }
        if (email.endsWith('@unsaac.edu.pe')) {
            return { tipo: 'docente', label: 'Docente UNSAAC', publico: 'docentes' };
        }
        return { tipo: 'publico', label: '', publico: 'todos' };
    };

    /**
     * Verificar si el usuario tiene un rol específico
     * @param {string|string[]} roles - Rol o array de roles permitidos
     * @returns {boolean}
     */
    const hasRole = (roles) => {
        const user = getCurrentUser();
        if (!user) return false;

        if (Array.isArray(roles)) {
            return roles.includes(user.rol);
        }
        return user.rol === roles;
    };

    /**
     * Cerrar sesión
     */
    const logout = async () => {
        try {
            // Llamar al backend para destruir la sesión PHP
            await API.post('auth/logout.php');
        } catch (error) {
            console.error('Error en logout:', error);
        } finally {
            // Limpiar sessionStorage
            sessionStorage.removeItem('tupa_session');
            sessionStorage.removeItem('tupa_user');
            sessionStorage.removeItem('tupa_redirect');

            // Redirigir al login
            window.location.href = 'login.html';
        }
    };

    /**
     * Mostrar notificación toast
     * @param {string} message - Mensaje a mostrar
     * @param {string} type - Tipo: success, error, warning, info
     * @param {number} duration - Duración en ms (default 4000)
     */
    const showToast = (message, type = 'info', duration = 4000) => {
        if (!toastContainer) {
            init();
        }

        // Iconos SVG para cada tipo
        const icons = {
            success: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`,
            error: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`,
            warning: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`,
            info: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`
        };

        // Crear elemento toast
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || icons.info}</span>
            <div class="toast-content">
                <p class="toast-message">${escapeHtml(message)}</p>
            </div>
            <button class="toast-close" aria-label="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        `;

        // Evento para cerrar toast
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            removeToast(toast);
        });

        // Agregar al contenedor
        toastContainer.appendChild(toast);

        // Auto-remover después de la duración
        setTimeout(() => {
            removeToast(toast);
        }, duration);
    };

    /**
     * Remover toast con animación
     * @param {HTMLElement} toast
     */
    const removeToast = (toast) => {
        toast.style.animation = 'toast-out 0.3s ease forwards';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    };

    /**
     * Formatear fecha en español
     * @param {string|Date} date - Fecha a formatear
     * @param {Object} options - Opciones de formato
     * @returns {string}
     */
    const formatDate = (date, options = {}) => {
        if (!date) return '-';

        const defaultOptions = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            ...options
        };

        try {
            const dateObj = typeof date === 'string' ? new Date(date) : date;

            // Verificar fecha válida
            if (isNaN(dateObj.getTime())) {
                return '-';
            }

            return dateObj.toLocaleDateString('es-PE', defaultOptions);
        } catch (e) {
            console.error('Error formatting date:', e);
            return '-';
        }
    };

    /**
     * Formatear fecha y hora
     * @param {string|Date} date
     * @returns {string}
     */
    const formatDateTime = (date) => {
        return formatDate(date, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    /**
     * Formatear fecha relativa (hace X tiempo)
     * @param {string|Date} date
     * @returns {string}
     */
    const formatRelativeDate = (date) => {
        if (!date) return '-';

        try {
            const dateObj = typeof date === 'string' ? new Date(date) : date;
            const now = new Date();
            const diffMs = now - dateObj;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Hace un momento';
            if (diffMins < 60) return `Hace ${diffMins} minuto${diffMins > 1 ? 's' : ''}`;
            if (diffHours < 24) return `Hace ${diffHours} hora${diffHours > 1 ? 's' : ''}`;
            if (diffDays < 7) return `Hace ${diffDays} día${diffDays > 1 ? 's' : ''}`;

            return formatDate(date, { year: 'numeric', month: 'short', day: 'numeric' });
        } catch (e) {
            return '-';
        }
    };

    /**
     * Formatear monto en soles
     * @param {number|string} amount - Monto a formatear
     * @returns {string}
     */
    const formatMoney = (amount) => {
        if (amount === null || amount === undefined || amount === '') {
            return 'S/. 0.00';
        }

        const num = parseFloat(amount);
        if (isNaN(num)) {
            return 'S/. 0.00';
        }

        return `S/. ${num.toLocaleString('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
    };

    /**
     * Formatear número de expediente
     * @param {string} expediente
     * @returns {string}
     */
    const formatExpediente = (expediente) => {
        if (!expediente) return '-';
        return expediente; // Ya viene formateado como EXP-2026-00001
    };

    /**
     * Escapar HTML para prevenir XSS
     * @param {string} text
     * @returns {string}
     */
    const escapeHtml = (text) => {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
     * Abrir modal
     * @param {string} modalId - ID del modal
     */
    const openModal = (modalId) => {
        const modal = document.getElementById(modalId);
        const backdrop = document.getElementById('modal-backdrop') || document.querySelector('.modal-backdrop');

        if (modal) {
            modal.classList.add('active');
            if (backdrop) {
                backdrop.classList.add('active');
            }
            document.body.style.overflow = 'hidden';
        }
    };

    /**
     * Cerrar modal
     * @param {string} modalId - ID del modal
     */
    const closeModal = (modalId) => {
        const modal = document.getElementById(modalId);
        const backdrop = document.getElementById('modal-backdrop') || document.querySelector('.modal-backdrop');

        if (modal) {
            modal.classList.remove('active');
            if (backdrop) {
                backdrop.classList.remove('active');
            }
            document.body.style.overflow = '';
        }
    };

    /**
     * Cerrar todos los modales
     */
    const closeAllModals = () => {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.querySelectorAll('.modal-backdrop.active').forEach(backdrop => {
            backdrop.classList.remove('active');
        });
        document.body.style.overflow = '';
    };

    /**
     * Mostrar loading overlay
     * @param {string} message - Mensaje opcional
     */
    const showLoading = (message = 'Cargando...') => {
        // Remover loading existente
        hideLoading();

        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div style="text-align: center;">
                <div class="spinner spinner-lg"></div>
                <p style="margin-top: 1rem; color: var(--text-secondary);">${escapeHtml(message)}</p>
            </div>
        `;
        document.body.appendChild(overlay);
    };

    /**
     * Ocultar loading overlay
     */
    const hideLoading = () => {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    };

    /**
     * Confirmar acción con modal
     * @param {Object} options - Opciones del diálogo
     * @returns {Promise<boolean>}
     */
    const confirm = (options = {}) => {
        return new Promise((resolve) => {
            const {
                title = '¿Está seguro?',
                message = '',
                confirmText = 'Confirmar',
                cancelText = 'Cancelar',
                type = 'warning' // warning, danger, info
            } = options;

            // Crear modal de confirmación
            const modalHtml = `
                <div class="modal-backdrop active" id="confirm-backdrop"></div>
                <div class="modal active" id="confirm-modal">
                    <div class="modal-header">
                        <h3 class="modal-title">${escapeHtml(title)}</h3>
                    </div>
                    <div class="modal-body">
                        <p>${escapeHtml(message)}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" id="confirm-cancel">${escapeHtml(cancelText)}</button>
                        <button class="btn btn-${type === 'danger' ? 'danger' : 'primary'}" id="confirm-accept">${escapeHtml(confirmText)}</button>
                    </div>
                </div>
            `;

            const container = document.createElement('div');
            container.id = 'confirm-container';
            container.innerHTML = modalHtml;
            document.body.appendChild(container);

            // Eventos
            const cleanup = () => {
                container.remove();
                document.body.style.overflow = '';
            };

            document.getElementById('confirm-cancel').addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            document.getElementById('confirm-accept').addEventListener('click', () => {
                cleanup();
                resolve(true);
            });

            document.getElementById('confirm-backdrop').addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            document.body.style.overflow = 'hidden';
        });
    };

    /**
     * Obtener parámetros de la URL
     * @param {string} param - Nombre del parámetro
     * @returns {string|null}
     */
    const getUrlParam = (param) => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    };

    /**
     * Establecer parámetros en la URL sin recargar
     * @param {Object} params - Parámetros a establecer
     */
    const setUrlParams = (params) => {
        const url = new URL(window.location.href);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        window.history.replaceState({}, '', url.toString());
    };

    /**
     * Debounce - retrasar ejecución de función
     * @param {Function} func
     * @param {number} wait
     * @returns {Function}
     */
    const debounce = (func, wait = 300) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    /**
     * Obtener texto del estado de trámite
     * @param {string} estado
     * @returns {string}
     */
    const getEstadoText = (estado) => {
        const estados = {
            'pendiente': 'Pendiente',
            'en_proceso': 'En Proceso',
            'observado': 'Observado',
            'aprobado': 'Aprobado',
            'rechazado': 'Rechazado'
        };
        return estados[estado] || estado;
    };

    /**
     * Obtener clase CSS del estado de trámite
     * @param {string} estado
     * @returns {string}
     */
    const getEstadoClass = (estado) => {
        return `badge-${estado}`;
    };

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Agregar estilos para animación de salida de toast
    const style = document.createElement('style');
    style.textContent = `
        @keyframes toast-out {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100%); }
        }
    `;
    document.head.appendChild(style);

    // API pública
    return {
        init,
        checkAuth,
        getCurrentUser,
        getUserTipo,
        hasRole,
        logout,
        showToast,
        formatDate,
        formatDateTime,
        formatRelativeDate,
        formatMoney,
        formatExpediente,
        escapeHtml,
        openModal,
        closeModal,
        closeAllModals,
        showLoading,
        hideLoading,
        confirm,
        getUrlParam,
        setUrlParams,
        debounce,
        getEstadoText,
        getEstadoClass
    };
})();

// Exportar para uso en módulos ES6 (si se requiere)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = App;
}
