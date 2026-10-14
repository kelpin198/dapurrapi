import "./bootstrap";

const authTokenKey = "dapurRapiToken";
const authUserKey = "dapurRapiUser";
let currentTransactionPage = 1;
window.dapurRapiFoods = [];
window.dapurRapiCategories = [];
window.dapurRapiToken = sessionStorage.getItem(authTokenKey);
window.dapurRapiUserRole = "user";

function escapeHtml(value) {
    return String(value ?? "").replace(
        /[&<>"']/g,
        (character) =>
            ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;",
            })[character],
    );
}

function showAuthNotice(message, type = "error") {
    const notice = document.querySelector("[data-notice]");

    if (!notice) return;

    notice.textContent = message;
    notice.classList.toggle("is-success", type === "success");
    notice.hidden = false;
}

async function requestApi(path, { token, ...options } = {}) {
    const headers = {
        Accept: "application/json",
        ...(options.body ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
    };
    const response = await fetch(`/api/${path}`, { ...options, headers });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = Object.values(payload.errors ?? {}).flat()[0];
        throw new Error(
            firstError ||
                payload.message ||
                "Permintaan belum berhasil. Coba lagi.",
        );
    }

    return payload;
}

function initializeAuthForms() {
    const loginForm = document.querySelector('[data-auth-form="login"]');
    const registerForm = document.querySelector('[data-auth-form="register"]');

    if (
        new URLSearchParams(window.location.search).has("registered") &&
        loginForm
    ) {
        showAuthNotice(
            "Akun berhasil dibuat. Silakan masuk dengan email dan kata sandimu.",
            "success",
        );
    }

    document.querySelectorAll("[data-unavailable-link]").forEach((link) => {
        link.addEventListener("click", (event) => {
            event.preventDefault();
            showAuthNotice("Hubungi pengelola dapur untuk bantuan akses akun.");
        });
    });

    loginForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = loginForm.querySelector('button[type="submit"]');
        const formData = Object.fromEntries(new FormData(loginForm));

        button.disabled = true;
        button.querySelector("span").textContent = "Sedang masuk...";

        try {
            const payload = await requestApi("login", {
                method: "POST",
                body: JSON.stringify(formData),
            });

            sessionStorage.setItem(authTokenKey, payload.data.token);
            sessionStorage.setItem(
                authUserKey,
                JSON.stringify(payload.data.user),
            );
            window.location.assign("/dashboard");
        } catch (error) {
            showAuthNotice(error.message);
            button.disabled = false;
            button.querySelector("span").textContent = "Masuk ke dashboard";
        }
    });

    registerForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = registerForm.querySelector('button[type="submit"]');
        const formData = Object.fromEntries(new FormData(registerForm));

        if (formData.password !== formData.password_confirmation) {
            showAuthNotice("Konfirmasi kata sandi belum sama.");
            return;
        }

        button.disabled = true;
        button.querySelector("span").textContent = "Membuat akun...";

        try {
            await requestApi("register", {
                method: "POST",
                body: JSON.stringify(formData),
            });

            window.location.assign("/login?registered=1");
        } catch (error) {
            showAuthNotice(error.message);
            button.disabled = false;
            button.querySelector("span").textContent = "Buat akun";
        }
    });
}

function unwrapData(payload) {
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    return [];
}

function collectionTotal(payload, items) {
    return Number(payload?.data?.meta?.total ?? items.length);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(amount) || 0);
}

function formatDate(value) {
    if (!value) return "—";

    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(new Date(value));
}

function formatDateTime(value) {
    if (!value) return "Belum pernah login";

    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}

function setTableMessage(selector, message, columns) {
    const body = document.querySelector(selector);
    if (body)
        body.innerHTML = `<tr><td class="table-empty" colspan="${columns}">${escapeHtml(message)}</td></tr>`;
}

function showDashboardNotice(message, type = "error") {
    const notice = document.querySelector("[data-dashboard-notice]");
    if (!notice) return;

    notice.textContent = message;
    notice.classList.toggle("is-success", type === "success");
    notice.hidden = false;
}

function initializeNavigation() {
    const shell = document.querySelector("[data-dashboard]");
    if (!shell) return;
    const menuToggle = document.querySelector("[data-menu-toggle]");
    const setMenuOpen = (isOpen) => {
        shell.classList.toggle("menu-open", isOpen);
        menuToggle?.setAttribute("aria-expanded", String(isOpen));
        menuToggle?.setAttribute(
            "aria-label",
            isOpen ? "Tutup menu" : "Buka menu",
        );
    };

    const navigate = (page) => {
        document.querySelectorAll("[data-panel]").forEach((panel) => {
            panel.classList.toggle("is-visible", panel.dataset.panel === page);
        });
        document.querySelectorAll(".nav-item[data-page]").forEach((item) => {
            item.classList.toggle("is-active", item.dataset.page === page);
        });

        const activeItem = document.querySelector(
            `.nav-item[data-page="${page}"]`,
        );
        const breadcrumb = document.querySelector("[data-breadcrumb]");
        if (breadcrumb && activeItem)
            breadcrumb.textContent =
                activeItem.querySelector("span:nth-child(2)").textContent;
        setMenuOpen(false);
    };

    document
        .querySelectorAll("[data-page], [data-switch-page]")
        .forEach((control) => {
            control.addEventListener("click", () =>
                navigate(control.dataset.page || control.dataset.switchPage),
            );
        });

    menuToggle?.addEventListener("click", () => {
        setMenuOpen(!shell.classList.contains("menu-open"));
    });
    document
        .querySelector("[data-menu-backdrop]")
        ?.addEventListener("click", () => setMenuOpen(false));

    let searchTimeout;
    document
        .querySelector("[data-food-search]")
        ?.addEventListener("input", (event) => {
            const query = event.currentTarget.value.trim();
            window.clearTimeout(searchTimeout);
            searchTimeout = window.setTimeout(async () => {
                try {
                    const payload = await requestApi(
                        `makanans?search=${encodeURIComponent(query)}`,
                        {
                            token: window.dapurRapiToken,
                        },
                    );
                    const foods = unwrapData(payload);
                    window.dapurRapiFoods = foods;
                    renderFoodsTable(foods, collectionTotal(payload, foods));
                } catch (error) {
                    showDashboardNotice(
                        error.message || "Pencarian belum dapat dilakukan.",
                    );
                }
            }, 250);
        });

    document
        .querySelector("[data-logout]")
        ?.addEventListener("click", async () => {
            const token = sessionStorage.getItem(authTokenKey);

            try {
                await requestApi("logout", { method: "POST", token });
            } catch {
                // Clear the local session even if the server has already expired the token.
            }

            sessionStorage.removeItem(authTokenKey);
            sessionStorage.removeItem(authUserKey);
            window.location.assign("/login");
        });
}

function renderFoodsTable(foods, total = foods.length) {
    const body = document.querySelector("[data-food-rows]");
    if (!body) return;

    const heading = document.querySelector("[data-foods-heading]");
    if (heading) heading.textContent = total.toLocaleString("id-ID");
    if (foods.length === 0) {
        setTableMessage(
            "[data-food-rows]",
            "Belum ada makanan yang cocok dengan pencarian ini.",
            6,
        );
        return;
    }

    const isAdmin = window.dapurRapiUserRole === "admin";
    body.innerHTML = foods
        .map((food) => {
            const actions = isAdmin
                ? `<div class="row-actions"><button class="table-action" type="button" data-food-edit="${Number(food.id)}">Ubah</button><button class="table-action table-action-danger" type="button" data-food-delete="${Number(food.id)}">Hapus</button></div>`
                : `<button class="table-action table-action-buy" type="button" data-food-buy="${Number(food.id)}" ${Number(food.stok) < 1 ? "disabled" : ""}>${Number(food.stok) < 1 ? "Habis" : "Beli"}</button>`;

            return `
		<tr>
			<td>${escapeHtml(food.nama_makanan)}</td>
			<td>${escapeHtml(food.kategori?.nama ?? food.nama_kategori ?? "Belum dikategorikan")}</td>
			<td>${escapeHtml(food.deskripsi || "—")}</td>
			<td>${escapeHtml(formatCurrency(food.harga))}</td>
			<td>${escapeHtml(food.stok ?? 0)} unit</td>
			<td>${actions}</td>
		</tr>
	`;
        })
        .join("");
}

function renderOverview(
    foods,
    categories,
    transactions,
    foodTotal,
    transactionTotal,
    topSellingFood,
) {
    const threshold = Number(
        document.querySelector("[data-low-stock-alert]")?.dataset
            .lowStockThreshold ?? 10,
    );
    const lowStockFoods = foods
        .filter((food) => Number(food.stok) <= threshold)
        .sort((first, second) => first.stok - second.stok);
    const isAdmin = window.dapurRapiUserRole === "admin";
    const metrics = {
        foods: foodTotal,
        categories: categories.length,
        "low-stock": isAdmin
            ? lowStockFoods.length
            : Number(topSellingFood?.quantity_sold ?? 0),
        transactions: transactionTotal,
    };

    Object.entries(metrics).forEach(([key, value]) => {
        const node = document.querySelector(`[data-metric="${key}"]`);
        if (node) node.textContent = value.toLocaleString("id-ID");
    });
    document.querySelector('[data-metric-label="low-stock"]').textContent =
        isAdmin ? "Stok menipis" : "Menu terlaris";
    document.querySelector('[data-metric-icon="low-stock"]').textContent =
        isAdmin ? "⌁" : "★";
    document.querySelector('[data-metric-note="low-stock"]').textContent =
        isAdmin
            ? `dari ${foods.length} menu di halaman ini`
            : (topSellingFood?.nama_makanan ?? "Belum ada pesanan");

    const lowStockAlert = document.querySelector("[data-low-stock-alert]");
    lowStockAlert.hidden = !isAdmin || lowStockFoods.length === 0;
    if (isAdmin && lowStockFoods.length > 0) {
        document.querySelector("[data-low-stock-alert-text]").textContent =
            `${lowStockFoods.length} menu memiliki stok ${threshold} atau kurang.`;
    }

    const lowStockBody = document.querySelector("[data-low-stock-rows]");
    if (lowStockBody) {
        if (lowStockFoods.length === 0) {
            setTableMessage(
                "[data-low-stock-rows]",
                "Semua stok terlihat aman. Tidak ada yang perlu direstok.",
                5,
            );
        } else {
            lowStockBody.innerHTML = lowStockFoods
                .slice(0, 5)
                .map(
                    (food) => `
				<tr>
					<td>${escapeHtml(food.nama_makanan)}</td>
					<td>${escapeHtml(food.kategori?.nama ?? food.nama_kategori ?? "Belum dikategorikan")}</td>
					<td>${escapeHtml(formatCurrency(food.harga))}</td>
					<td>${escapeHtml(food.stok ?? 0)} unit</td>
					<td><span class="status-pill">Perlu restok</span></td>
				</tr>
			`,
                )
                .join("");
        }
    }

    const recentFoods = [...foods].slice(-4).reverse();
    const foodImages = [
        "https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=520&q=78",
        "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=520&q=78",
        "https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=520&q=78",
        "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?auto=format&fit=crop&w=520&q=78",
    ];
    const recentContainer = document.querySelector("[data-recent-foods]");
    if (recentContainer) {
        recentContainer.innerHTML = recentFoods.length
            ? recentFoods
                  .map(
                      (food, index) => `
			<article class="recent-card">
				<div class="recent-photo" role="img" aria-label="Foto ${escapeHtml(food.nama_makanan)}" style="background-image:url('${foodImages[index]}')"></div>
				<div class="recent-copy"><strong>${escapeHtml(food.nama_makanan)}</strong><span>${escapeHtml(food.kategori?.nama ?? food.nama_kategori ?? "Menu dapur")}</span></div>
			</article>
		`,
                  )
                  .join("")
            : '<div class="empty-inline">Belum ada menu. Daftar makananmu akan muncul di sini.</div>';
    }
}

function renderCategories(categories, foods) {
    const grid = document.querySelector("[data-category-grid]");
    if (!grid) return;

    if (categories.length === 0) {
        grid.innerHTML =
            '<div class="empty-inline">Belum ada kategori yang terdaftar.</div>';
        return;
    }

    const isAdmin = window.dapurRapiUserRole === "admin";
    grid.innerHTML = categories
        .map((category) => {
            const count = foods.filter(
                (food) =>
                    food.kategori?.id === category.id ||
                    food.nama_kategori === category.nama_kategori ||
                    food.kategori?.nama === category.nama_kategori,
            ).length;
            const initials = category.nama_kategori
                .trim()
                .slice(0, 1)
                .toLocaleUpperCase("id-ID");

            const actions = isAdmin
                ? `<div class="category-actions"><button class="icon-button" type="button" data-category-edit="${Number(category.id)}" aria-label="Ubah ${escapeHtml(category.nama_kategori)}" title="Ubah kategori">✎</button><button class="icon-button" type="button" data-category-delete="${Number(category.id)}" aria-label="Hapus ${escapeHtml(category.nama_kategori)}" title="Hapus kategori">×</button></div>`
                : "";

            return `<article class="category-item"><span class="category-stamp" aria-hidden="true">${escapeHtml(initials)}</span><span class="category-copy"><strong>${escapeHtml(category.nama_kategori)}</strong><small>${count} menu pada halaman ini</small></span>${actions}</article>`;
        })
        .join("");
}

function renderTransactions(transactions, pagination = {}) {
    const body = document.querySelector("[data-transaction-rows]");
    if (!body) return;

    currentTransactionPage = Number(pagination.current_page ?? 1);
    const lastPage = Number(pagination.last_page ?? 1);
    const paginationControls = document.querySelector(
        "[data-transaction-pagination]",
    );

    if (paginationControls) {
        paginationControls.hidden = lastPage <= 1;
        paginationControls.querySelector(
            "[data-transaction-previous]",
        ).disabled = currentTransactionPage <= 1;
        paginationControls.querySelector("[data-transaction-next]").disabled =
            currentTransactionPage >= lastPage;
        paginationControls.querySelector(
            "[data-transaction-page-label]",
        ).textContent =
            `Halaman ${currentTransactionPage} dari ${lastPage} · ${Number(pagination.total ?? transactions.length).toLocaleString("id-ID")} transaksi`;
    }

    const isAdmin = window.dapurRapiUserRole === "admin";
    const columns = isAdmin ? 7 : 6;
    const headers = document.querySelector("[data-transaction-head]");

    if (headers) {
        headers.innerHTML = isAdmin
            ? "<th>Nomor</th><th>Pemesan</th><th>Makanan</th><th>Tanggal</th><th>Total</th><th>Status pesanan</th><th>Pembayaran</th>"
            : "<th>Nomor</th><th>Makanan</th><th>Tanggal</th><th>Total</th><th>Status pesanan</th><th>Pembayaran</th>";
    }

    if (transactions.length === 0) {
        setTableMessage(
            "[data-transaction-rows]",
            isAdmin
                ? "Belum ada transaksi dari pengguna."
                : "Belum ada transaksi pada akun ini.",
            columns,
        );
        filterTransactions();
        return;
    }

    const statusOptions = [
        ["pending", "Menunggu"],
        ["diproses", "Diproses"],
        ["selesai", "Selesai"],
        ["dibatalkan", "Dibatalkan"],
    ];

    body.innerHTML = [...transactions]
        .reverse()
        .map((transaction) => {
            const status = String(transaction.status ?? "menunggu");
            const statusClass = ["selesai", "completed", "success"].includes(
                status.toLowerCase(),
            )
                ? "is-ok"
                : "is-pending";
            const statusLabel =
                statusOptions.find(([value]) => value === status)?.[1] ??
                status;
            const payment = transaction.payments?.at(-1);
            const paymentMethods = {
                bank_transfer: "Transfer bank",
                qris: "QRIS",
                cash_on_pickup: "Tunai saat ambil",
            };
            const paymentStatuses = {
                pending: "Menunggu pembayaran",
                processing: "Memeriksa pembayaran",
                paid: "Lunas",
                failed: "Gagal",
                expired: "Kedaluwarsa",
                refunded: "Dikembalikan",
            };
            const paymentStatusOptions = Object.entries(paymentStatuses);
            const isRefunded = payment?.status === "refunded";
            const isCompletedOrPaid =
                status === "selesai" || payment?.status === "paid";
            const isLocked = isRefunded || isCompletedOrPaid;
            const foodItems = transaction.items?.length
                ? `<ul class="transaction-food-list">${transaction.items
                      .map(
                          (item) =>
                              `<li><span>${escapeHtml(item.nama_makanan ?? "Menu dihapus")}</span><small>× ${escapeHtml(item.qty)}</small></li>`,
                      )
                      .join("")}</ul>`
                : '<span class="transaction-food-empty">Rincian tidak tersedia</span>';
            const statusDisplay = isAdmin
                ? `<select class="text-field transaction-status" data-transaction-status="${Number(transaction.id)}" data-current-status="${escapeHtml(status)}" aria-label="Ubah status transaksi ${escapeHtml(String(transaction.id))}" ${isLocked ? `disabled title="${isRefunded ? "Transaksi sudah dikembalikan" : "Transaksi sudah selesai/lunas"}"` : ""}>${statusOptions
                      .map(
                          ([value, label]) =>
                              `<option value="${value}" ${value === status ? "selected" : ""}>${label}</option>`,
                      )
                      .join("")}</select>`
                : `<span class="status-pill ${statusClass}">${escapeHtml(statusLabel)}</span>`;
            const paymentDisplay = payment
                ? `<span class="payment-method-label">${escapeHtml(paymentMethods[payment.payment_method] ?? payment.payment_method)}</span>${
                      isAdmin
                          ? `<select class="text-field payment-status-select" data-transaction-payment-status="${Number(transaction.id)}" data-current-status="${escapeHtml(payment.status)}" aria-label="Ubah status pembayaran transaksi ${escapeHtml(String(transaction.id))}" ${isLocked ? `disabled title="${isRefunded ? "Pembayaran sudah dikembalikan" : "Transaksi sudah selesai/lunas"}"` : ""}>${paymentStatusOptions
                                .map(
                                    ([value, label]) =>
                                        `<option value="${value}" ${value === payment.status ? "selected" : ""}>${escapeHtml(label)}</option>`,
                                )
                                .join("")}</select>`
                          : `<small class="payment-status-label ${["failed", "expired"].includes(payment.status) ? "is-failed" : ""}">${escapeHtml(paymentStatuses[payment.status] ?? payment.status)}</small>`
                  }`
                : '<span class="payment-status-label">Belum ada data</span>';

            return `
            <tr class="${isAdmin && isRefunded ? "transaction-row-refunded" : isAdmin && isCompletedOrPaid ? "transaction-row-completed" : ""}" ${isAdmin && isLocked ? 'aria-disabled="true"' : ""}>
				<td>#${escapeHtml(String(transaction.id).padStart(4, "0"))}</td>
                ${isAdmin ? `<td>${escapeHtml(transaction.user?.name ?? "Pengguna")}</td>` : ""}
                <td>${foodItems}</td>
				<td>${escapeHtml(formatDate(transaction.created_at))}</td>
				<td>${escapeHtml(formatCurrency(transaction.total_harga))}</td>
				<td>${statusDisplay}</td>
                <td>${paymentDisplay}</td>
			</tr>
		`;
        })
        .join("");

    filterTransactions();
}

function transactionRequestPath(page) {
    const params = new URLSearchParams({ page: String(page) });
    const search = document
        .querySelector("[data-transaction-search]")
        ?.value.trim();

    if (search) params.set("search", search);

    return `transaksis?${params.toString()}`;
}

async function loadTransactionPage(page) {
    const previousButton = document.querySelector(
        "[data-transaction-previous]",
    );
    const nextButton = document.querySelector("[data-transaction-next]");
    previousButton.disabled = true;
    nextButton.disabled = true;

    try {
        const payload = await requestApi(transactionRequestPath(page), {
            token: window.dapurRapiToken,
        });
        renderTransactions(unwrapData(payload), payload.pagination);

        const activeCount = document.querySelector(
            '[data-metric="transactions"]',
        );
        if (activeCount && payload.active_transactions_count !== undefined) {
            activeCount.textContent = Number(
                payload.active_transactions_count,
            ).toLocaleString("id-ID");
        }
    } catch (error) {
        previousButton.disabled = currentTransactionPage <= 1;
        nextButton.disabled = false;
        showDashboardNotice(error.message);
    }
}

function filterTransactions() {
    const body = document.querySelector("[data-transaction-rows]");
    const tools = document.querySelector("[data-admin-transaction-tools]");
    if (!body || !tools || tools.hidden) return;

    const query = document
        .querySelector("[data-transaction-search]")
        .value.trim()
        .toLocaleLowerCase("id-ID");
    const selectedStatus = document.querySelector(
        "[data-transaction-status-filter]",
    ).value;
    const rows = [...body.querySelectorAll("tr")].filter(
        (row) => !row.querySelector(".table-empty, .table-loading"),
    );
    let visibleCount = 0;

    rows.forEach((row) => {
        const status = row.querySelector("[data-transaction-status]")?.value;
        const rowText = [...row.cells]
            .map((cell) => {
                const select = cell.querySelector("select");
                return (
                    select?.selectedOptions[0]?.textContent ?? cell.textContent
                );
            })
            .join(" ");
        const matchesQuery = rowText.toLocaleLowerCase("id-ID").includes(query);
        const matchesStatus = !selectedStatus || status === selectedStatus;
        row.hidden = !(matchesQuery && matchesStatus);
        if (!row.hidden) visibleCount += 1;
    });

    document.querySelector("[data-transaction-result-count]").textContent =
        `${visibleCount.toLocaleString("id-ID")} dari ${rows.length.toLocaleString("id-ID")} pesanan`;
}

function renderLoginActivity(users) {
    const body = document.querySelector("[data-login-activity-rows]");
    if (!body) return;

    if (users.length === 0) {
        setTableMessage(
            "[data-login-activity-rows]",
            "Belum ada akun pengguna.",
            4,
        );
        return;
    }

    body.innerHTML = users
        .map(
            (user) => `
                <tr>
                    <td>${escapeHtml(user.name)}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${escapeHtml(user.role === "admin" ? "Admin" : "Pengguna")}</td>
                    <td>${escapeHtml(formatDateTime(user.last_login_at))}</td>
                </tr>
            `,
        )
        .join("");
}

function renderProfile(profile) {
    const nameParts = profile.name.trim().split(/\s+/);
    const hour = new Date().getHours();
    const greeting =
        hour >= 5 && hour < 11
            ? "Selamat pagi"
            : hour >= 11 && hour < 15
              ? "Selamat siang"
              : hour >= 15 && hour < 18
                ? "Selamat sore"
                : "Selamat malam";

    document.querySelector("[data-user-name]").textContent = profile.name;
    document.querySelector("[data-user-email]").textContent = profile.email;
    document.querySelector("[data-greeting-label]").textContent = greeting;
    document.querySelector("[data-greeting-name]").textContent = nameParts[0];
    document.querySelector("[data-avatar]").textContent = nameParts
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase("id-ID");
}

async function loadDashboardData(token) {
    const [profilePayload, foodPayload, categoryPayload, transactionPayload] =
        await Promise.all([
            requestApi("me", { token }),
            requestApi("makanans", { token }),
            requestApi("kategoris", { token }),
            requestApi(transactionRequestPath(currentTransactionPage), {
                token,
            }),
        ]);
    const profile = profilePayload.data;
    const foods = unwrapData(foodPayload);
    const categories = unwrapData(categoryPayload);
    const transactions = unwrapData(transactionPayload);
    const foodTotal = collectionTotal(foodPayload, foods);
    const transactionTotal = Number(
        transactionPayload.active_transactions_count ??
            collectionTotal(transactionPayload, transactions),
    );

    window.dapurRapiFoods = foods;
    window.dapurRapiCategories = categories;
    window.dapurRapiUserRole = profile.role === "admin" ? "admin" : "user";
    renderProfile(profile);
    document.querySelector("[data-user-role-label]").textContent =
        window.dapurRapiUserRole === "admin" ? "Administrator" : "Pengguna";
    document.querySelector("[data-profile-open]").hidden =
        window.dapurRapiUserRole !== "user";
    document.querySelector("[data-admin-stock-panel]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-login-activity-nav]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-admin-transaction-tools]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-create-food]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-create-category]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector('[data-count="foods"]').textContent =
        foodTotal.toLocaleString("id-ID");
    document.querySelector("[data-transactions-description]").textContent =
        window.dapurRapiUserRole === "admin"
            ? "Pesanan semua pengguna dan pengelolaan status transaksi."
            : "Riwayat transaksi yang tercatat di akunmu.";
    document.querySelector("[data-transactions-heading]").textContent =
        window.dapurRapiUserRole === "admin"
            ? "Semua pesanan"
            : "Transaksi saya";

    renderOverview(
        foods,
        categories,
        transactions,
        foodTotal,
        transactionTotal,
        transactionPayload.top_selling_food,
    );
    renderFoodsTable(foods, foodTotal);
    renderCategories(categories, foods);
    renderTransactions(transactions, transactionPayload.pagination);

    if (window.dapurRapiUserRole === "admin") {
        try {
            const loginActivityPayload = await requestApi(
                "admin/login-activity",
                { token },
            );
            renderLoginActivity(unwrapData(loginActivityPayload));
        } catch {
            setTableMessage(
                "[data-login-activity-rows]",
                "Aktivitas login belum dapat dimuat.",
                4,
            );
        }
    }
}

function openFoodDialog(food = null) {
    const dialog = document.querySelector("[data-food-dialog]");
    const form = document.querySelector("[data-food-form]");
    const categorySelect = document.querySelector("[data-food-category]");

    if (!window.dapurRapiCategories.length) {
        showDashboardNotice(
            "Tambahkan kategori lebih dulu sebelum membuat menu.",
        );
        return;
    }

    categorySelect.innerHTML = window.dapurRapiCategories
        .map(
            (category) =>
                `<option value="${Number(category.id)}">${escapeHtml(category.nama_kategori)}</option>`,
        )
        .join("");
    form.reset();
    document.querySelector("[data-food-id]").value = food?.id ?? "";
    document.querySelector("[data-food-dialog-title]").textContent = food
        ? "Ubah makanan"
        : "Tambah makanan";
    document.querySelector('[name="nama_makanan"]').value =
        food?.nama_makanan ?? "";
    document.querySelector('[name="kategori_id"]').value =
        food?.kategori?.id ??
        food?.kategori_id ??
        window.dapurRapiCategories[0].id;
    document.querySelector('[name="harga"]').value = food?.harga ?? "";
    document.querySelector('[name="stok"]').value = food?.stok ?? "";
    document.querySelector('[name="deskripsi"]').value = food?.deskripsi ?? "";
    dialog.showModal();
}

function openCategoryDialog(category = null) {
    const form = document.querySelector("[data-category-form]");
    form.reset();
    document.querySelector("[data-category-id]").value = category?.id ?? "";
    document.querySelector("[data-category-dialog-title]").textContent =
        category ? "Ubah kategori" : "Tambah kategori";
    document.querySelector('[name="nama_kategori"]').value =
        category?.nama_kategori ?? "";
    document.querySelector("[data-category-dialog]").showModal();
}

function setPurchaseStep(step) {
    const isPaymentStep = step === "payment";
    document.querySelectorAll("[data-purchase-step]").forEach((section) => {
        section.hidden = section.dataset.purchaseStep !== step;
    });
    document.querySelector("[data-purchase-eyebrow]").textContent =
        isPaymentStep ? "LANGKAH 2 DARI 2" : "LANGKAH 1 DARI 2";
    document.querySelector("[data-purchase-title]").textContent = isPaymentStep
        ? "Pembayaran"
        : "Pesanan";
}

function updatePaymentSummary() {
    const foodId = Number(document.querySelector("[data-purchase-id]").value);
    const quantity = Number(document.querySelector('[name="qty"]').value);
    const food = window.dapurRapiFoods.find(
        (item) => Number(item.id) === foodId,
    );
    if (!food) return;

    const total = Number(food.harga) * quantity;
    document.querySelector("[data-payment-item-name]").textContent =
        food.nama_makanan;
    document.querySelector("[data-payment-item-quantity]").textContent =
        `${quantity} × ${formatCurrency(food.harga)}`;
    document.querySelector("[data-payment-total]").textContent =
        formatCurrency(total);
    updatePaymentInstructions();
}

function updatePaymentInstructions() {
    const method = document.querySelector(
        '[name="payment_method"]:checked',
    )?.value;
    const settings = JSON.parse(
        document.querySelector("[data-payment-settings]").textContent,
    );
    const bankPanel = document.querySelector("[data-bank-payment-panel]");
    const qrisPanel = document.querySelector("[data-qris-payment-panel]");
    const accountDetails = document.querySelector(
        "[data-payment-account-details]",
    );
    const account = settings.banks[0];
    bankPanel.hidden = method !== "bank_transfer";
    qrisPanel.hidden = method !== "qris";
    accountDetails.hidden = method !== "bank_transfer" || !account;
    document.querySelector("[data-bank-unavailable]").hidden =
        method !== "bank_transfer" || Boolean(account);

    if (account) {
        document.querySelector("[data-payment-bank-name]").textContent =
            account.name;
        document.querySelector("[data-payment-account-number]").textContent =
            account.account_number;
        document.querySelector("[data-payment-account-name]").textContent =
            account.account_name;
    }

    const qrisImage = document.querySelector("[data-qris-code]");
    const qrisUnavailable = document.querySelector("[data-qris-unavailable]");
    const hasQrisImage = Boolean(settings.qrisImageUrl);
    const hasQrisSource = hasQrisImage || Boolean(settings.qrisPayload);
    qrisImage.hidden = method !== "qris" || !hasQrisSource;
    qrisUnavailable.hidden = method !== "qris" || hasQrisSource;

    if (method === "qris" && hasQrisSource) {
        qrisImage.src = hasQrisImage
            ? settings.qrisImageUrl
            : `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(settings.qrisPayload)}`;
    }

    const instructions = {
        bank_transfer: account
            ? `Transfer tepat ${document.querySelector("[data-payment-total]").textContent} dari bank mana pun ke rekening ${account.name} yang tertera.`
            : "Transfer bank belum aktif.",
        qris: hasQrisSource
            ? "Pindai QRIS merchant, lalu bayar sesuai total pesanan. Pembayaran belum diverifikasi otomatis."
            : "QRIS belum dikonfigurasi pengelola.",
        cash_on_pickup:
            "Pesanan dicatat menunggu pembayaran. Siapkan pembayaran tunai saat mengambil pesanan.",
    };

    document.querySelector("[data-payment-instructions]").textContent =
        instructions[method] ?? instructions.cash_on_pickup;
    document.querySelector("[data-payment-confirm]").disabled =
        (method === "bank_transfer" && !account) ||
        (method === "qris" && !hasQrisSource);
}

function initializePaymentMethods() {
    const settings = JSON.parse(
        document.querySelector("[data-payment-settings]").textContent,
    );
    const bankMethod = document.querySelector(
        '[name="payment_method"][value="bank_transfer"]',
    );
    const qrisMethod = document.querySelector(
        '[name="payment_method"][value="qris"]',
    );

    bankMethod.disabled = false;
    qrisMethod.disabled = false;
    document.querySelector("[data-bank-method-hint]").textContent =
        settings.banks.length > 0
            ? "Transfer dari bank mana pun ke rekening tujuan ini"
            : "Rekening tujuan belum tersedia";
    document.querySelector("[data-qris-method-hint]").textContent =
        settings.qrisImageUrl || settings.qrisPayload
            ? "Pindai QRIS merchant"
            : "Belum tersedia: QRIS merchant belum dikonfigurasi";

    updatePaymentInstructions();
}

function initializeDashboardActions() {
    if (!document.querySelector("[data-dashboard]")) return;

    initializePaymentMethods();
    let transactionSearchTimeout;
    document
        .querySelector("[data-transaction-search]")
        ?.addEventListener("input", () => {
            window.clearTimeout(transactionSearchTimeout);
            transactionSearchTimeout = window.setTimeout(
                () => loadTransactionPage(1),
                250,
            );
        });
    document
        .querySelector("[data-transaction-status-filter]")
        ?.addEventListener("change", filterTransactions);
    document
        .querySelector("[data-transaction-previous]")
        ?.addEventListener("click", () =>
            loadTransactionPage(Math.max(1, currentTransactionPage - 1)),
        );
    document
        .querySelector("[data-transaction-next]")
        ?.addEventListener("click", () =>
            loadTransactionPage(currentTransactionPage + 1),
        );

    document
        .querySelector("[data-profile-open]")
        ?.addEventListener("click", () => {
            const nameField = document.querySelector("[data-profile-name]");
            nameField.value =
                document.querySelector("[data-user-name]").textContent;
            document.querySelector("[data-profile-dialog]").showModal();
        });

    document
        .querySelector("[data-profile-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const name = new FormData(form).get("name").trim();

            button.disabled = true;
            try {
                const payload = await requestApi("me/profile", {
                    method: "PUT",
                    token: window.dapurRapiToken,
                    body: JSON.stringify({ name }),
                });
                renderProfile(payload.data);
                sessionStorage.setItem(
                    authUserKey,
                    JSON.stringify(payload.data),
                );
                document.querySelector("[data-profile-dialog]").close();
                showDashboardNotice(
                    "Nama profil berhasil diperbarui.",
                    "success",
                );
            } catch (error) {
                showDashboardNotice(error.message);
            } finally {
                button.disabled = false;
            }
        });

    document
        .querySelector("[data-create-food]")
        ?.addEventListener("click", () => {
            openFoodDialog();
        });
    document
        .querySelector("[data-create-category]")
        ?.addEventListener("click", () => openCategoryDialog());

    document.querySelectorAll("[data-close-dialog]").forEach((button) => {
        button.addEventListener("click", () =>
            button.closest("dialog").close(),
        );
    });

    document
        .querySelector("[data-food-rows]")
        ?.addEventListener("click", async (event) => {
            const button = event.target.closest("button");
            if (!button) return;

            const foodId = Number(
                button.dataset.foodEdit ??
                    button.dataset.foodDelete ??
                    button.dataset.foodBuy,
            );
            const food = window.dapurRapiFoods.find(
                (item) => Number(item.id) === foodId,
            );
            if (!food) return;

            if (button.hasAttribute("data-food-edit")) {
                openFoodDialog(food);
                return;
            }

            if (button.hasAttribute("data-food-delete")) {
                if (
                    !window.confirm(
                        `Hapus ${food.nama_makanan} dari daftar makanan?`,
                    )
                )
                    return;

                try {
                    await requestApi(`makanans/${foodId}`, {
                        method: "DELETE",
                        token: window.dapurRapiToken,
                    });
                    showDashboardNotice("Makanan berhasil dihapus.", "success");
                    await loadDashboardData(window.dapurRapiToken);
                } catch (error) {
                    showDashboardNotice(error.message);
                }
                return;
            }

            if (button.hasAttribute("data-food-buy")) {
                document.querySelector("[data-purchase-id]").value = food.id;
                document.querySelector("[data-purchase-name]").textContent =
                    food.nama_makanan;
                document.querySelector("[data-purchase-price]").textContent =
                    `${formatCurrency(food.harga)} per porsi · stok ${food.stok} unit`;
                const quantity = document.querySelector('[name="qty"]');
                quantity.max = food.stok;
                quantity.value = "1";
                const paymentSettings = JSON.parse(
                    document.querySelector("[data-payment-settings]")
                        .textContent,
                );
                const defaultMethod = paymentSettings.banks.length
                    ? "bank_transfer"
                    : paymentSettings.qrisPayload
                      ? "qris"
                      : "cash_on_pickup";
                document.querySelector(
                    `[name="payment_method"][value="${defaultMethod}"]`,
                ).checked = true;
                updatePaymentInstructions();
                setPurchaseStep("details");
                updatePaymentSummary();
                document.querySelector("[data-purchase-dialog]").showModal();
            }
        });

    document
        .querySelector('[name="qty"]')
        ?.addEventListener("input", updatePaymentSummary);
    document
        .querySelectorAll('[name="payment_method"]')
        .forEach((option) =>
            option.addEventListener("change", updatePaymentInstructions),
        );

    document
        .querySelector("[data-payment-next]")
        ?.addEventListener("click", () => {
            const quantity = Number(
                document.querySelector('[name="qty"]').value,
            );
            const food = window.dapurRapiFoods.find(
                (item) =>
                    Number(item.id) ===
                    Number(document.querySelector("[data-purchase-id]").value),
            );

            if (!food || quantity < 1 || quantity > Number(food.stok)) {
                showDashboardNotice(
                    "Jumlah pesanan melebihi stok yang tersedia.",
                );
                return;
            }

            updatePaymentSummary();
            setPurchaseStep("payment");
        });

    document
        .querySelector("[data-payment-back]")
        ?.addEventListener("click", () => setPurchaseStep("details"));

    document
        .querySelector("[data-category-grid]")
        ?.addEventListener("click", async (event) => {
            const button = event.target.closest("button");
            if (!button) return;

            const categoryId = Number(
                button.dataset.categoryEdit ?? button.dataset.categoryDelete,
            );
            const category = window.dapurRapiCategories.find(
                (item) => Number(item.id) === categoryId,
            );
            if (!category) return;

            if (button.hasAttribute("data-category-edit")) {
                openCategoryDialog(category);
                return;
            }

            if (!window.confirm(`Hapus kategori ${category.nama_kategori}?`))
                return;

            try {
                await requestApi(`kategoris/${categoryId}`, {
                    method: "DELETE",
                    token: window.dapurRapiToken,
                });
                showDashboardNotice("Kategori berhasil dihapus.", "success");
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                showDashboardNotice(error.message);
            }
        });

    document
        .querySelector("[data-transaction-rows]")
        ?.addEventListener("change", async (event) => {
            const paymentSelect = event.target.closest(
                "[data-transaction-payment-status]",
            );

            if (paymentSelect) {
                const transactionId = Number(
                    paymentSelect.dataset.transactionPaymentStatus,
                );
                const previousStatus = paymentSelect.dataset.currentStatus;
                const status = paymentSelect.value;
                if (status === previousStatus) return;

                paymentSelect.disabled = true;
                try {
                    await requestApi(
                        `transaksis/${transactionId}/payment-status`,
                        {
                            method: "PUT",
                            token: window.dapurRapiToken,
                            body: JSON.stringify({ status }),
                        },
                    );
                    showDashboardNotice(
                        "Status pembayaran berhasil diperbarui.",
                        "success",
                    );
                    await loadDashboardData(window.dapurRapiToken);
                } catch (error) {
                    paymentSelect.value = previousStatus;
                    showDashboardNotice(error.message);
                } finally {
                    paymentSelect.disabled = false;
                }
                return;
            }

            const select = event.target.closest("[data-transaction-status]");
            if (!select) return;

            const transactionId = Number(select.dataset.transactionStatus);
            const previousStatus = select.dataset.currentStatus;
            const status = select.value;
            if (status === previousStatus) return;

            select.disabled = true;
            try {
                await requestApi(`transaksis/${transactionId}/status`, {
                    method: "PUT",
                    token: window.dapurRapiToken,
                    body: JSON.stringify({ status }),
                });
                showDashboardNotice(
                    "Status transaksi berhasil diperbarui.",
                    "success",
                );
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                select.value = previousStatus;
                showDashboardNotice(error.message);
            } finally {
                select.disabled = false;
            }
        });

    document
        .querySelector("[data-food-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const values = Object.fromEntries(new FormData(form));
            const foodId = values.id;
            const payload = {
                nama_makanan: values.nama_makanan,
                kategori_id: Number(values.kategori_id),
                harga: Number(values.harga),
                stok: Number(values.stok),
                deskripsi: values.deskripsi,
            };

            button.disabled = true;
            try {
                await requestApi(foodId ? `makanans/${foodId}` : "makanans", {
                    method: foodId ? "PUT" : "POST",
                    token: window.dapurRapiToken,
                    body: JSON.stringify(payload),
                });
                document.querySelector("[data-food-dialog]").close();
                showDashboardNotice(
                    foodId
                        ? "Perubahan makanan tersimpan."
                        : "Makanan berhasil ditambahkan.",
                    "success",
                );
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                showDashboardNotice(error.message);
            } finally {
                button.disabled = false;
            }
        });

    document
        .querySelector("[data-category-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const values = Object.fromEntries(new FormData(form));
            const categoryId = values.id;

            button.disabled = true;
            try {
                await requestApi(
                    categoryId ? `kategoris/${categoryId}` : "kategoris",
                    {
                        method: categoryId ? "PUT" : "POST",
                        token: window.dapurRapiToken,
                        body: JSON.stringify({
                            nama_kategori: values.nama_kategori,
                        }),
                    },
                );
                document.querySelector("[data-category-dialog]").close();
                showDashboardNotice(
                    categoryId
                        ? "Perubahan kategori tersimpan."
                        : "Kategori berhasil ditambahkan.",
                    "success",
                );
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                showDashboardNotice(error.message);
            } finally {
                button.disabled = false;
            }
        });

    document
        .querySelector("[data-purchase-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const foodId = Number(
                document.querySelector("[data-purchase-id]").value,
            );
            const quantity = Number(
                document.querySelector('[name="qty"]').value,
            );
            const food = window.dapurRapiFoods.find(
                (item) => Number(item.id) === foodId,
            );

            if (!food || quantity < 1 || quantity > Number(food.stok)) {
                showDashboardNotice(
                    "Jumlah pesanan melebihi stok yang tersedia.",
                );
                return;
            }

            const paymentMethod = document.querySelector(
                '[name="payment_method"]:checked',
            ).value;

            if (
                !document.querySelector('[data-purchase-step="payment"]').hidden
            ) {
                button.disabled = true;
                try {
                    await requestApi("transaksis", {
                        method: "POST",
                        token: window.dapurRapiToken,
                        body: JSON.stringify({
                            items: [{ makanan_id: foodId, qty: quantity }],
                            payment_method: paymentMethod,
                        }),
                    });
                    document.querySelector("[data-purchase-dialog]").close();
                    showDashboardNotice(
                        "Pesanan tercatat. Silakan konfirmasi pembayaran dengan pengelola.",
                        "success",
                    );
                    await loadDashboardData(window.dapurRapiToken);
                } catch (error) {
                    showDashboardNotice(error.message);
                } finally {
                    button.disabled = false;
                }
                return;
            }
        });
}

async function initializeDashboard() {
    const dashboard = document.querySelector("[data-dashboard]");
    if (!dashboard) return;

    const token = sessionStorage.getItem(authTokenKey);
    if (!token) {
        window.location.replace("/login");
        return;
    }
    window.dapurRapiToken = token;

    document.querySelector("[data-date]").textContent = new Intl.DateTimeFormat(
        "id-ID",
        {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric",
        },
    ).format(new Date());

    try {
        await loadDashboardData(token);
    } catch (error) {
        if (/unauthorized|unauthenticated/i.test(error.message)) {
            sessionStorage.removeItem(authTokenKey);
            sessionStorage.removeItem(authUserKey);
            window.location.replace("/login");
            return;
        }

        showDashboardNotice(
            error.message ||
                "Data belum dapat dimuat. Periksa koneksi lalu muat ulang halaman.",
        );
        setTableMessage(
            "[data-low-stock-rows]",
            "Data stok belum dapat dimuat.",
            5,
        );
        setTableMessage(
            "[data-food-rows]",
            "Data makanan belum dapat dimuat.",
            6,
        );
        setTableMessage(
            "[data-transaction-rows]",
            "Data transaksi belum dapat dimuat.",
            4,
        );
        document.querySelector("[data-category-grid]").innerHTML =
            '<div class="empty-inline">Data kategori belum dapat dimuat.</div>';
    }
}

initializeAuthForms();
initializeNavigation();
initializeDashboardActions();
initializeDashboard();
