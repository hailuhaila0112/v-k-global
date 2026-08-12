// Dark Mode Toggle
function initDarkMode() {
  const isDark = localStorage.getItem("vk_dark") === "true";
  document.documentElement.classList.toggle("dark", isDark);

  const toggleBtn = document.getElementById("dark-toggle");
  if (toggleBtn) {
    toggleBtn.innerHTML = isDark ? "☀️" : "🌙";
    toggleBtn.addEventListener("click", () => {
      const current = document.documentElement.classList.contains("dark");
      document.documentElement.classList.toggle("dark", !current);
      localStorage.setItem("vk_dark", !current);
      toggleBtn.innerHTML = !current ? "☀️" : "🌙";
    });
  }
}

// Header Scroll Effect
window.addEventListener("scroll", () => {
  const header = document.querySelector("header");
  if (header) {
    header.classList.toggle("scrolled", window.scrollY > 20);
  }
});

// Global State & Services Integration
let cart = cartService.getCart();
let cachedProducts = []; // Cache products fetched from productService to avoid global products variable

// Fetch and cache products globally on load
async function loadProductsToCache() {
  try {
    const data = await productService.getAll();
    cachedProducts = data || [];
  } catch (error) {
    console.error("Failed to load products to cache:", error);
    cachedProducts = [];
  }
}

function saveCart() {
  cartService.saveCart(cart);
  updateCartBadge();
}

function addToCart(productId, quantity = 1) {
  const existing = cart.find(item => item.productId === productId);
  if (existing) {
    existing.quantity += quantity;
  } else {
    cart.push({ productId, quantity });
  }
  saveCart();
  showToast("🛒 Đã thêm sản phẩm vào giỏ hàng thành công!");
}

function updateCartBadge() {
  const badges = document.querySelectorAll("#cart-badge");
  const total = cart.reduce((sum, item) => sum + item.quantity, 0);
  badges.forEach(badge => {
    if (badge) {
      badge.textContent = total;
    }
  });
}

// Toast Notification
function showToast(message) {
  let container = document.querySelector(".toast-container");
  if (!container) {
    container = document.createElement("div");
    container.className = "toast-container";
    document.body.appendChild(container);
  }

  const toast = document.createElement("div");
  toast.className = "toast";
  toast.innerHTML = `<span>${message}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = "slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) reverse forwards";
    setTimeout(() => {
      toast.remove();
    }, 300);
  }, 3000);
}

// Format Currency
function formatCurrency(amount) {
  return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(amount);
}

// User Nav
function renderNavbarUser() {
  const container = document.getElementById("user-nav");
  if (!container) return;

  const user = authService.getCurrentUser();

  if (!user) {
    container.innerHTML = `<a href="account.html" class="btn btn-primary">Đăng nhập</a>`;
    return;
  }

  const initial = (user.name || user.email || "U")[0].toUpperCase();
  const isAdmin = user.role === "admin";

  container.innerHTML = `
    <div class="user-nav">
      <div class="user-nav-btn" onclick="toggleUserMenu(event)">
        <div class="user-nav-avatar">${initial}</div>
        <span class="user-nav-name">${user.name || user.email}</span>
        <span class="user-nav-arrow">▼</span>
      </div>
      <div class="user-nav-dropdown" id="userDropdown">
        <a href="account.html">👤 Tài khoản của tôi</a>
        ${isAdmin ? `<a href="admin.html">📊 Admin Dashboard</a>` : ""}
        <div class="divider"></div>
        <button class="danger" onclick="handleNavLogout()">🚪 Đăng xuất</button>
      </div>
    </div>
  `;
}

function toggleUserMenu(e) {
  if (e) e.stopPropagation();
  const dropdown = document.getElementById("userDropdown");
  const btn = dropdown?.previousElementSibling;
  if (dropdown) {
    const isOpen = dropdown.classList.contains("open");
    document.querySelectorAll(".user-nav-dropdown").forEach(d => d.classList.remove("open"));
    document.querySelectorAll(".user-nav-btn").forEach(b => b.classList.remove("open"));
    if (!isOpen) {
      dropdown.classList.add("open");
      btn?.classList.add("open");
    }
  }
}

function handleNavLogout() {
  authService.logout();
  renderNavbarUser();
  showToast("👋 Đã đăng xuất!");
  if (window.location.pathname.includes("account")) {
    location.reload();
  }
}

document.addEventListener("click", (e) => {
  if (!e.target.closest(".user-nav")) {
    document.querySelectorAll(".user-nav-dropdown").forEach(d => d.classList.remove("open"));
    document.querySelectorAll(".user-nav-btn").forEach(b => b.classList.remove("open"));
  }
});

// Quick View Modal
async function openQuickView(productId) {
  let p = cachedProducts.find(prod => prod.id === productId);
  if (!p) {
    p = await productService.getById(productId);
  }
  if (!p) return;

  let modal = document.getElementById("quickview-modal");
  if (!modal) {
    modal = document.createElement("div");
    modal.id = "quickview-modal";
    modal.className = "modal";
    document.body.appendChild(modal);
  }

  const featuresHtml = p.features ? (Array.isArray(p.features) ? p.features : JSON.parse(p.features)).map(f => `<li>✨ ${f}</li>`).join("") : "";
  const specsObj = p.specs ? (typeof p.specs === 'string' ? JSON.parse(p.specs) : p.specs) : {};
  const specsHtml = Object.entries(specsObj).map(([k, v]) => `
    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border);">
      <span style="color: var(--muted); font-weight: 600;">${k}</span>
      <span style="font-weight: 700;">${v}</span>
    </div>
  `).join("");

  const cat = p.category_name || p.category || '';
  const origPrice = p.original_price || p.originalPrice || 0;
  const shortDesc = p.short_description || p.shortDescription || '';
  const reviews = p.reviews_count || p.reviews || 0;

  modal.innerHTML = `
    <div class="modal-content">
      <button class="modal-close" onclick="closeQuickView()">✕</button>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; padding: 30px;">
        <div>
          <img src="${p.image}" alt="${p.name}" style="width: 100%; height: auto; border-radius: 16px; object-fit: cover; box-shadow: var(--shadow);">
        </div>
        <div style="display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <span style="font-size: 12px; color: var(--brand); font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">${cat}</span>
            <h2 style="font-size: 24px; font-weight: 800; margin: 8px 0 12px; line-height: 1.3;">${p.name}</h2>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
              <div class="stars">⭐⭐⭐⭐⭐ <span style="color: var(--muted); font-size: 13px; font-weight: 600;">(${reviews} đánh giá)</span></div>
            </div>
            <div style="margin-bottom: 20px;">
              <span style="font-size: 28px; font-weight: 800; color: var(--brand);">${formatCurrency(p.price)}</span>
              ${origPrice ? `<span style="font-size: 16px; color: var(--muted); text-decoration: line-through; margin-left: 10px;">${formatCurrency(origPrice)}</span>` : ""}
            </div>
            <p style="color: var(--muted); font-size: 14px; line-height: 1.6; margin-bottom: 20px;">${p.description || shortDesc}</p>
            <ul style="list-style: none; display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px; font-size: 14px; font-weight: 600;">
              ${featuresHtml}
            </ul>
            <div style="margin-bottom: 24px;">
              <h4 style="font-size: 15px; font-weight: 800; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Thông số kỹ thuật</h4>
              ${specsHtml}
            </div>
          </div>
          <button class="btn btn-primary" style="width: 100%; padding: 14px;" onclick="addToCart('${p.id}'); closeQuickView();">🛒 Thêm vào giỏ hàng</button>
        </div>
      </div>
    </div>
  `;

  modal.classList.add("active");
  document.body.style.overflow = "hidden";
}

function closeQuickView() {
  const modal = document.getElementById("quickview-modal");
  if (modal) {
    modal.classList.remove("active");
    document.body.style.overflow = "";
  }
}

// Chatbot Logic
function initChatbot() {
  let btn = document.getElementById("chatbot-btn");
  let windowEl = document.getElementById("chatbot-window");

  if (!btn) {
    btn = document.createElement("button");
    btn.id = "chatbot-btn";
    btn.className = "chatbot-btn";
    btn.innerHTML = "💬";
    btn.title = "Trợ lý AI";
    document.body.appendChild(btn);
  }

  if (!windowEl) {
    windowEl = document.createElement("div");
    windowEl.id = "chatbot-window";
    windowEl.className = "chatbot-window";
    windowEl.innerHTML = `
      <div class="chatbot-header">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 22px;">🤖</span>
          <div>
            <div style="font-weight: 800; font-size: 14px;">Trợ lý AI V.K Global</div>
            <div style="font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 5px;"><span class="chatbot-status-dot"></span> Đang hoạt động</div>
          </div>
        </div>
        <button onclick="toggleChatbot()" style="background: rgba(255,255,255,0.15); border: none; color: white; font-size: 16px; cursor: pointer; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">✕</button>
      </div>
      <div class="chatbot-messages" id="chatbot-messages">
        <div class="chat-msg bot">🤖 Xin chào! Tôi là trợ lý AI của <strong>V.K Global</strong>.<br><br>Tôi có thể giúp gì cho bạn?<br>• 🚗 Tư vấn xe golf tự hành<br>• 📡 Cảm biến LiDAR, Camera AI<br>• 🔧 Linh kiện, động cơ, vi điều khiển<br>• 📋 Thông tin dự án<br>• 📞 Liên hệ kỹ sư</div>
      </div>
      <div class="chatbot-quick-replies">
        <button class="chip" onclick="askQuick('Xe golf tự hành')">🚗 Xe golf</button>
        <button class="chip" onclick="askQuick('Cảm biến LiDAR')">📡 LiDAR</button>
        <button class="chip" onclick="askQuick('Camera AI')">📷 Camera AI</button>
        <button class="chip" onclick="askQuick('Dự án')">📋 Dự án</button>
        <button class="chip" onclick="askQuick('Liên hệ tư vấn')">📞 Liên hệ</button>
        <button class="chip" onclick="askQuick('Sản phẩm')">🛒 Sản phẩm</button>
      </div>
      <div class="chatbot-input-area">
        <input type="text" class="chatbot-input" id="chatbot-input" placeholder="Nhập câu hỏi..." onkeypress="handleChatPress(event)" autocomplete="off">
        <button class="btn btn-primary" style="padding: 10px 16px; border-radius: 12px; flex-shrink: 0;" onclick="sendChatMessage()">Gửi</button>
      </div>
    `;
    document.body.appendChild(windowEl);
  }

  btn.addEventListener("click", toggleChatbot);
}

function askQuick(question) {
  const input = document.getElementById("chatbot-input");
  if (input) {
    input.value = question;
    sendChatMessage();
  }
}

function toggleChatbot() {
  const windowEl = document.getElementById("chatbot-window");
  if (windowEl) {
    windowEl.classList.toggle("active");
  }
}

function handleChatPress(e) {
  if (e.key === "Enter") {
    sendChatMessage();
  }
}

function sendChatMessage() {
  const input = document.getElementById("chatbot-input");
  const messagesContainer = document.getElementById("chatbot-messages");
  if (!input || !messagesContainer || !input.value.trim()) return;

  const userText = input.value.trim();
  input.value = "";

  // Append User Message
  const userMsg = document.createElement("div");
  userMsg.className = "chat-msg user";
  userMsg.textContent = userText;
  messagesContainer.appendChild(userMsg);
  messagesContainer.scrollTop = messagesContainer.scrollHeight;

  // Typing Indicator
  const typingMsg = document.createElement("div");
  typingMsg.className = "chat-msg bot typing-indicator-msg";
  typingMsg.innerHTML = `
    <div class="typing-indicator">
      <span></span>
      <span></span>
      <span></span>
    </div>
  `;
  messagesContainer.appendChild(typingMsg);
  messagesContainer.scrollTop = messagesContainer.scrollHeight;

  // AI Response Logic via Backend API
  fetch(`${API_BASE_URL}/chat`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message: userText })
  })
    .then(res => res.json())
    .then(res => {
      typingMsg.remove();
      const botMsg = document.createElement("div");
      botMsg.className = "chat-msg bot";
      botMsg.innerHTML = res.success ? res.data.reply : "Xin lỗi, tôi đang gặp sự cố kết nối.";
      messagesContainer.appendChild(botMsg);
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    })
    .catch(err => {
      console.error("Chatbot API error:", err);
      typingMsg.remove();
      const botMsg = document.createElement("div");
      botMsg.className = "chat-msg bot";
      botMsg.textContent = "Xin lỗi, tôi không thể kết nối đến máy chủ lúc này.";
      messagesContainer.appendChild(botMsg);
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    });
}

document.addEventListener("DOMContentLoaded", async () => {
  initDarkMode();
  renderNavbarUser();
  updateCartBadge();
  initChatbot();
  await loadProductsToCache();
});
