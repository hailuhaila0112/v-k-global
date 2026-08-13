// API Ready Services for V.K Global Shop
// Seamlessly integrated with PHP RESTful API Backend.

const API_BASE_URL = "http://localhost/DATTDN/backend/public/api";

// Helper to get Auth Headers
function getAuthHeaders() {
  const user = authService.getCurrentUser();
  const headers = { "Content-Type": "application/json" };
  if (user && user.token) {
    headers["Authorization"] = `Bearer ${user.token}`;
  }
  return headers;
}

// 1. AUTH SERVICE
const authService = {
  async login(email, password) {
    try {
      const response = await fetch(`${API_BASE_URL}/auth/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password })
      });
      const result = await response.json();
      if (result.success && result.data) {
        localStorage.setItem("vk_user", JSON.stringify(result.data));
      }
      return result;
    } catch (error) {
      console.error("Login error:", error);
      return { success: false, message: "Không thể kết nối đến máy chủ" };
    }
  },

  async register(name, email, password, phone) {
    try {
      const response = await fetch(`${API_BASE_URL}/auth/register`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, password, phone })
      });
      return await response.json();
    } catch (error) {
      console.error("Register error:", error);
      return { success: false, message: "Không thể kết nối đến máy chủ" };
    }
  },

  async logout() {
    localStorage.removeItem("vk_user");
    return { success: true };
  },

  getCurrentUser() {
    return JSON.parse(localStorage.getItem("vk_user")) || null;
  }
};

// 2. PRODUCT SERVICE
const productService = {
  async getAll() {
    try {
      const response = await fetch(`${API_BASE_URL}/products`);
      const result = await response.json();
      const list = result.success && result.data ? result.data : (typeof products !== 'undefined' ? [...products] : []);
      return (list || []).map(p => ({ ...p, id: p.id != null && !isNaN(Number(p.id)) ? Number(p.id) : p.id }));
    } catch (error) {
      console.error("Fetch products failed, using local fallback:", error);
      const list = typeof products !== 'undefined' ? [...products] : [];
      return list.map(p => ({ ...p, id: p.id != null && !isNaN(Number(p.id)) ? Number(p.id) : p.id }));
    }
  },

  async getBySlug(slug) {
    return typeof products !== 'undefined' ? products.find(p => p.slug === slug) || null : null;
  },

  async getById(id) {
    try {
      const response = await fetch(`${API_BASE_URL}/products/${id}`);
      const result = await response.json();
      if (result.success && result.data) {
        const p = result.data;
        return { ...p, id: p.id != null && !isNaN(Number(p.id)) ? Number(p.id) : p.id };
      }
      if (typeof products !== 'undefined') {
        return products.find(p => String(p.id) === String(id)) || null;
      }
      return null;
    } catch (error) {
      return typeof products !== 'undefined' ? products.find(p => String(p.id) === String(id)) || null : null;
    }
  }
};

// 3. CART SERVICE
const cartService = {
  getCart() {
    try {
      const raw = JSON.parse(localStorage.getItem("vk_cart")) || [];
      if (!Array.isArray(raw)) return [];
      // Normalize legacy keys
      return raw.map(item => ({
        productId: item.productId ?? item.product_id ?? item.id,
        quantity: Number(item.quantity ?? item.qty ?? 1) || 1,
        name: item.name || '',
        price: Number(item.price) || 0,
        image: item.image || ''
      })).filter(item => item.productId != null && item.productId !== '');
    } catch (e) {
      return [];
    }
  },

  saveCart(cart) {
    localStorage.setItem("vk_cart", JSON.stringify(cart));
  }
};

// 4. WISHLIST SERVICE
const wishlistService = {
  getWishlist() {
    return JSON.parse(localStorage.getItem("vk_wishlist")) || [];
  },

  toggle(productId) {
    let list = this.getWishlist();
    const idx = list.indexOf(productId);
    if (idx > -1) {
      list.splice(idx, 1);
      localStorage.setItem("vk_wishlist", JSON.stringify(list));
      return { action: "removed", list };
    } else {
      list.push(productId);
      localStorage.setItem("vk_wishlist", JSON.stringify(list));
      return { action: "added", list };
    }
  }
};

// 5. ORDER SERVICE
const orderService = {
  async createOrder(orderDetails) {
    try {
      const response = await fetch(`${API_BASE_URL}/orders/checkout`, {
        method: "POST",
        headers: getAuthHeaders(),
        body: JSON.stringify(orderDetails)
      });
      const result = await response.json();
      if (result.success) {
        return result;
      } else {
        throw new Error(result.message || "Lỗi đặt hàng từ API");
      }
    } catch (e) {
      console.error("API Checkout failed:", e);
      return { success: false, message: e.message || "Không thể kết nối đến máy chủ" };
    }
  },

  async getMyOrders() {
    try {
      const response = await fetch(`${API_BASE_URL}/orders/my`, {
        headers: getAuthHeaders()
      });
      const result = await response.json();
      if (result.success) return result.data;
      return [];
    } catch (e) {
      console.error("Get orders failed:", e);
      return [];
    }
  },

  async getPayOSStatus(orderCode) {
    try {
      const response = await fetch(`${API_BASE_URL}/payments/payos/status/${orderCode}`, {
        headers: getAuthHeaders()
      });
      return await response.json();
    } catch (e) {
      console.error("Get PayOS status failed:", e);
      return { success: false, message: "Không thể kiểm tra trạng thái thanh toán" };
    }
  },

  async getPayOSQrInfo(orderCode) {
    try {
      const response = await fetch(`${API_BASE_URL}/payments/payos/qr/${orderCode}`, {
        headers: getAuthHeaders()
      });
      return await response.json();
    } catch (e) {
      console.error("Get PayOS QR info failed:", e);
      return { success: false, message: "Không thể tải thông tin mã QR" };
    }
  }
};

// 5b. SETTINGS SERVICE
const settingsService = {
  async getShipping() {
    try {
      const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
      const timer = controller ? setTimeout(() => controller.abort(), 8000) : null;
      const response = await fetch(`${API_BASE_URL}/settings/shipping`, controller ? { signal: controller.signal } : undefined);
      if (timer) clearTimeout(timer);
      const result = await response.json();
      if (result.success && result.data) {
        const fee = Number(result.data.shipping_fee);
        const threshold = Number(result.data.free_shipping_threshold);
        return {
          shipping_fee: Number.isFinite(fee) ? fee : 30000,
          free_shipping_threshold: Number.isFinite(threshold) ? threshold : 15000000,
          shipping_rate_id: result.data.shipping_rate_id || null,
          name: result.data.name || 'Giao hàng tiêu chuẩn'
        };
      }
    } catch (e) {
      console.error("Get shipping settings failed:", e);
    }
    return { shipping_fee: 30000, free_shipping_threshold: 15000000, shipping_rate_id: null, name: 'Giao hàng tiêu chuẩn' };
  },

  async getShippingRates() {
    try {
      const response = await fetch(`${API_BASE_URL}/shipping-rates`);
      const result = await response.json();
      return result.success && result.data ? result.data : [];
    } catch (e) {
      console.error("Get shipping rates failed:", e);
      return [];
    }
  }
};

// 6. CONTACT SERVICE
const contactService = {
  async submit(name, email, message) {
    try {
      const response = await fetch(`${API_BASE_URL}/contact`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name, email, message })
      });
      return await response.json();
    } catch (error) {
      console.error("Contact API error:", error);
      return { success: false, message: "Không thể kết nối đến máy chủ" };
    }
  }
};

// 7. PROJECT SERVICE
const projectService = {
  async getAll() {
    try {
      const response = await fetch(`${API_BASE_URL}/projects`);
      const result = await response.json();
      return result.success && result.data ? result.data : (typeof projects !== 'undefined' ? [...projects] : []);
    } catch (error) {
      console.error("Fetch projects failed, using local fallback:", error);
      return typeof projects !== 'undefined' ? [...projects] : [];
    }
  }
};
