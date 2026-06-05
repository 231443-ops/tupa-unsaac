/**
 * Admin.js - Utilidades del panel de administración
 * TUPA UNSAAC
 * Requiere: api.js, main.js (App)
 */

const Admin = (() => {
    // Roles con acceso al panel de administración
    const ROLES_PERMITIDOS = ['admin', 'jefe', 'operador'];
    // Roles que pueden aprobar / rechazar trámites
    const ROLES_RESOLUCION = ['admin', 'jefe'];

    /**
     * Verifica autenticación y rol de administración.
     * Redirige al dashboard de usuario si el rol no está permitido.
     * @returns {Object|null} Usuario autorizado o null
     */
    const requireAdmin = () => {
        const user = App.checkAuth();
        if (!user) return null;

        if (!ROLES_PERMITIDOS.includes(user.rol)) {
            App.showToast('Acceso denegado. Se requiere rol de administración.', 'error');
            setTimeout(() => {
                window.location.href = '../dashboard.html';
            }, 1500);
            return null;
        }
        return user;
    };

    /**
     * Indica si el usuario puede aprobar/rechazar trámites.
     * @param {Object} user
     * @returns {boolean}
     */
    const canResolve = (user) => {
        return !!user && ROLES_RESOLUCION.includes(user.rol);
    };

    /**
     * Inicializa el layout común (navbar + sidebar) del panel admin.
     * @param {Object} user
     */
    const initLayout = (user) => {
        if (!user) return;
        const initial = (user.nombre || 'A').charAt(0).toUpperCase();

        const avatar = document.getElementById('user-avatar');
        const name = document.getElementById('user-name');
        const role = document.getElementById('user-role');
        if (avatar) avatar.textContent = initial;
        if (name) name.textContent = user.nombre || 'Administrador';
        if (role) role.textContent = user.rol || '';

        const logout = document.getElementById('logout-link');
        if (logout) {
            logout.addEventListener('click', (e) => {
                e.preventDefault();
                App.logout();
            });
        }
    };

    /**
     * Devuelve un badge HTML de urgencia según los días restantes.
     * @param {Object} tramite - Con dias_restantes, urgente, vencido
     * @returns {string}
     */
    const urgencyBadge = (tramite) => {
        if (tramite.vencido) {
            return `<span class="badge badge-rechazado">Vencido</span>`;
        }
        if (tramite.urgente) {
            return `<span class="badge badge-observado">${tramite.dias_restantes} día(s)</span>`;
        }
        return `<span class="badge badge-aprobado">${tramite.dias_restantes} día(s)</span>`;
    };

    return {
        ROLES_PERMITIDOS,
        ROLES_RESOLUCION,
        requireAdmin,
        canResolve,
        initLayout,
        urgencyBadge
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = Admin;
}
