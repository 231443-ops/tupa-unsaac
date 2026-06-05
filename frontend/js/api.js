/**
 * API.js - Capa centralizada de comunicación con el backend
 * TUPA UNSAAC
 */

const API = (() => {
    // Configuración base
    const BASE_URL = '../backend';

    // Timeout por defecto (30 segundos)
    const DEFAULT_TIMEOUT = 30000;

    /**
     * Obtener headers comunes para las peticiones
     * @returns {Headers}
     */
    const getHeaders = () => {
        const headers = new Headers({
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        });

        // Incluir token de sesión si existe
        const session = sessionStorage.getItem('tupa_session');
        if (session) {
            try {
                const sessionData = JSON.parse(session);
                if (sessionData.token) {
                    headers.append('Authorization', `Bearer ${sessionData.token}`);
                }
            } catch (e) {
                console.error('Error parsing session:', e);
            }
        }

        return headers;
    };

    /**
     * Manejar respuesta de la API
     * @param {Response} response
     * @returns {Promise<Object>}
     */
    const handleResponse = async (response) => {
        let data;

        try {
            data = await response.json();
        } catch (e) {
            throw new Error('Error al procesar la respuesta del servidor');
        }

        if (!response.ok) {
            // Manejar errores específicos por código HTTP
            switch (response.status) {
                case 401:
                    // Sesión expirada o no autorizado
                    sessionStorage.removeItem('tupa_session');
                    sessionStorage.removeItem('tupa_user');
                    window.location.href = 'login.html';
                    throw new Error(data.message || 'Sesión expirada. Por favor, inicie sesión nuevamente.');

                case 403:
                    throw new Error(data.message || 'No tiene permisos para realizar esta acción.');

                case 404:
                    throw new Error(data.message || 'Recurso no encontrado.');

                case 409:
                    throw new Error(data.message || 'Conflicto con los datos existentes.');

                case 422:
                    throw new Error(data.message || 'Datos de entrada inválidos.');

                case 500:
                    throw new Error(data.message || 'Error interno del servidor. Intente más tarde.');

                default:
                    throw new Error(data.message || `Error ${response.status}`);
            }
        }

        return data;
    };

    /**
     * Manejar errores de red
     * @param {Error} error
     */
    const handleNetworkError = (error) => {
        if (error.name === 'AbortError') {
            throw new Error('La solicitud tardó demasiado. Por favor, intente nuevamente.');
        }

        if (!navigator.onLine) {
            throw new Error('Sin conexión a internet. Verifique su conexión.');
        }

        throw new Error(error.message || 'Error de conexión con el servidor.');
    };

    /**
     * Realizar petición GET
     * @param {string} endpoint - Ruta del endpoint (sin BASE_URL)
     * @param {Object} params - Parámetros query opcionales
     * @param {number} timeout - Timeout en ms
     * @returns {Promise<Object>}
     */
    const apiGet = async (endpoint, params = {}, timeout = DEFAULT_TIMEOUT) => {
        // Construir URL con parámetros query
        const url = new URL(`${BASE_URL}/${endpoint}`, window.location.origin);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                url.searchParams.append(key, params[key]);
            }
        });

        // Configurar timeout con AbortController
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: getHeaders(),
                credentials: 'include', // Incluir cookies de sesión PHP
                signal: controller.signal
            });

            clearTimeout(timeoutId);
            return await handleResponse(response);

        } catch (error) {
            clearTimeout(timeoutId);
            handleNetworkError(error);
        }
    };

    /**
     * Realizar petición POST
     * @param {string} endpoint - Ruta del endpoint (sin BASE_URL)
     * @param {Object} data - Datos a enviar en el body
     * @param {number} timeout - Timeout en ms
     * @returns {Promise<Object>}
     */
    const apiPost = async (endpoint, data = {}, timeout = DEFAULT_TIMEOUT) => {
        const url = new URL(`${BASE_URL}/${endpoint}`, window.location.origin).toString();

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'include',
                body: JSON.stringify(data),
                signal: controller.signal
            });

            clearTimeout(timeoutId);
            return await handleResponse(response);

        } catch (error) {
            clearTimeout(timeoutId);
            handleNetworkError(error);
        }
    };

    /**
     * Realizar petición PUT
     * @param {string} endpoint - Ruta del endpoint (sin BASE_URL)
     * @param {Object} data - Datos a enviar en el body
     * @param {number} timeout - Timeout en ms
     * @returns {Promise<Object>}
     */
    const apiPut = async (endpoint, data = {}, timeout = DEFAULT_TIMEOUT) => {
        const url = new URL(`${BASE_URL}/${endpoint}`, window.location.origin).toString();

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(url, {
                method: 'PUT',
                headers: getHeaders(),
                credentials: 'include',
                body: JSON.stringify(data),
                signal: controller.signal
            });

            clearTimeout(timeoutId);
            return await handleResponse(response);

        } catch (error) {
            clearTimeout(timeoutId);
            handleNetworkError(error);
        }
    };

    /**
     * Realizar petición DELETE
     * @param {string} endpoint - Ruta del endpoint (sin BASE_URL)
     * @param {number} timeout - Timeout en ms
     * @returns {Promise<Object>}
     */
    const apiDelete = async (endpoint, timeout = DEFAULT_TIMEOUT) => {
        const url = new URL(`${BASE_URL}/${endpoint}`, window.location.origin).toString();

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: getHeaders(),
                credentials: 'include',
                signal: controller.signal
            });

            clearTimeout(timeoutId);
            return await handleResponse(response);

        } catch (error) {
            clearTimeout(timeoutId);
            handleNetworkError(error);
        }
    };

    /**
     * Subir archivos (multipart/form-data)
     * @param {string} endpoint - Ruta del endpoint (sin BASE_URL)
     * @param {FormData} formData - FormData con archivos y datos
     * @param {Function} onProgress - Callback para progreso de subida (opcional)
     * @param {number} timeout - Timeout en ms
     * @returns {Promise<Object>}
     */
    const apiUpload = async (endpoint, formData, onProgress = null, timeout = 60000) => {
        const url = new URL(`${BASE_URL}/${endpoint}`, window.location.origin).toString();

        // Si hay callback de progreso, usar XMLHttpRequest
        if (onProgress && typeof onProgress === 'function') {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();

                xhr.open('POST', url, true);
                xhr.withCredentials = true;

                // Agregar token de sesión si existe
                const session = sessionStorage.getItem('tupa_session');
                if (session) {
                    try {
                        const sessionData = JSON.parse(session);
                        if (sessionData.token) {
                            xhr.setRequestHeader('Authorization', `Bearer ${sessionData.token}`);
                        }
                    } catch (e) {
                        console.error('Error parsing session:', e);
                    }
                }

                // Evento de progreso
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        onProgress(percent);
                    }
                });

                // Evento de completado
                xhr.addEventListener('load', () => {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(data);
                        } else {
                            reject(new Error(data.message || `Error ${xhr.status}`));
                        }
                    } catch (e) {
                        reject(new Error('Error al procesar la respuesta'));
                    }
                });

                // Evento de error
                xhr.addEventListener('error', () => {
                    reject(new Error('Error de conexión al subir archivo'));
                });

                // Timeout
                xhr.timeout = timeout;
                xhr.addEventListener('timeout', () => {
                    reject(new Error('Tiempo de espera agotado al subir archivo'));
                });

                // Enviar
                xhr.send(formData);
            });
        }

        // Sin callback de progreso, usar fetch
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        try {
            // Para FormData, no establecer Content-Type (el navegador lo hace automáticamente)
            const headers = new Headers({
                'Accept': 'application/json'
            });

            const session = sessionStorage.getItem('tupa_session');
            if (session) {
                try {
                    const sessionData = JSON.parse(session);
                    if (sessionData.token) {
                        headers.append('Authorization', `Bearer ${sessionData.token}`);
                    }
                } catch (e) {
                    console.error('Error parsing session:', e);
                }
            }

            const response = await fetch(url, {
                method: 'POST',
                headers: headers,
                credentials: 'include',
                body: formData,
                signal: controller.signal
            });

            clearTimeout(timeoutId);
            return await handleResponse(response);

        } catch (error) {
            clearTimeout(timeoutId);
            handleNetworkError(error);
        }
    };

    // API pública
    return {
        BASE_URL,
        get: apiGet,
        post: apiPost,
        put: apiPut,
        delete: apiDelete,
        upload: apiUpload
    };
})();

// Exportar para uso en módulos ES6 (si se requiere)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
}
