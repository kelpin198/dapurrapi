import "./bootstrap";

const authTokenKey = "dapurRapiToken";
const authUserKey = "dapurRapiUser";
const pendingApprovalStorageKey = "dapurRapiPendingApprovalIds";
const foodImageSearchCache = new Map();
const restaurantLogoMaxBytes = 1_500_000;
let currentTransactionPage = 1;
let latestKnownAdminTransactionId = 0;
let adminOrderWatchInitialized = false;
let adminOrderPollingTimer = null;
let userApprovalPollingTimer = null;
let userApprovalPollInProgress = false;
let recentCarouselPage = 0;
window.dapurRapiFoods = [];
window.dapurRapiDrinks = [];
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

function updateRecentCarouselControls() {
    const grid = document.querySelector("[data-recent-foods]");
    const controls = document.querySelector("[data-recent-controls]");
    const slider = document.querySelector("[data-recent-slider]");

    if (!grid || !controls || !slider) return;

    const foodCount = grid.querySelectorAll(".recent-card").length;
    const pageCount = Math.ceil(foodCount / 4);
    recentCarouselPage = Math.min(
        recentCarouselPage,
        Math.max(pageCount - 1, 0),
    );
    controls.hidden = pageCount === 0;
    slider.max = String(Math.max(pageCount - 1, 0));
    slider.value = String(recentCarouselPage);
    slider.disabled = pageCount <= 1;
    slider.style.setProperty(
        "--recent-thumb-width",
        `${Math.min(100, (4 / foodCount) * 100)}%`,
    );
}

function moveRecentCarouselToPage(page) {
    const grid = document.querySelector("[data-recent-foods]");
    if (!grid) return;

    const pageCount = Math.ceil(
        grid.querySelectorAll(".recent-card").length / 4,
    );
    recentCarouselPage = Math.max(0, Math.min(page, pageCount - 1));

    const pageStartCard =
        grid.querySelectorAll(".recent-card")[recentCarouselPage * 4];
    if (pageStartCard) {
        grid.scrollTo({
            left: pageStartCard.offsetLeft - grid.offsetLeft,
            behavior: "smooth",
        });
    }

    updateRecentCarouselControls();
}

function searchCommonsFoodImage(foodName) {
    const normalizedName = foodName.trim().toLocaleLowerCase("id-ID");

    if (!foodImageSearchCache.has(normalizedName)) {
        const parameters = new URLSearchParams({
            action: "query",
            generator: "search",
            gsrsearch: normalizedName,
            gsrnamespace: "6",
            gsrlimit: "1",
            prop: "imageinfo",
            iiprop: "url|extmetadata",
            iiurlwidth: "520",
            format: "json",
            origin: "*",
        });
        const searchRequest = fetch(
            `https://commons.wikimedia.org/w/api.php?${parameters}`,
        )
            .then((response) => (response.ok ? response.json() : null))
            .then((payload) => {
                const imageInfo = Object.values(payload?.query?.pages ?? {})[0]
                    ?.imageinfo?.[0];

                if (!imageInfo?.thumburl || !imageInfo.descriptionurl) {
                    return null;
                }

                const imageUrl = new URL(imageInfo.thumburl);
                const sourceUrl = new URL(imageInfo.descriptionurl);

                if (
                    imageUrl.protocol !== "https:" ||
                    sourceUrl.protocol !== "https:" ||
                    !imageUrl.hostname.endsWith("wikimedia.org") ||
                    !sourceUrl.hostname.endsWith("wikimedia.org")
                ) {
                    return null;
                }

                const metadata = imageInfo.extmetadata ?? {};
                const artistMarkup = metadata.Artist?.value ?? "";
                const author = new DOMParser()
                    .parseFromString(artistMarkup, "text/html")
                    .body.textContent.trim();

                return {
                    url: imageUrl.href,
                    credit: {
                        author: author || "Wikimedia Commons contributor",
                        license:
                            metadata.LicenseShortName?.value ??
                            "Wikimedia Commons",
                        url: sourceUrl.href,
                    },
                };
            })
            .catch(() => null);

        foodImageSearchCache.set(normalizedName, searchRequest);
    }

    return foodImageSearchCache.get(normalizedName);
}

function showAuthNotice(message, type = "error") {
    const notice = document.querySelector("[data-notice]");

    if (!notice) return;

    notice.textContent = message;
    notice.classList.toggle("is-success", type === "success");
    notice.classList.toggle(
        "is-order-confirmation",
        type === "order-confirmation",
    );
    notice.hidden = false;
}

async function requestApi(path, { token, ...options } = {}) {
    const isFormData = options.body instanceof FormData;
    const headers = {
        Accept: "application/json",
        ...(options.body && !isFormData
            ? { "Content-Type": "application/json" }
            : {}),
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

async function optimizeRestaurantLogo(file) {
    if (file.size <= restaurantLogoMaxBytes) return file;
    if (typeof createImageBitmap !== "function") {
        throw new Error(
            "Browser tidak dapat memproses gambar. Coba unggah logo lebih kecil.",
        );
    }

    const image = await createImageBitmap(file);
    let scale = Math.min(1, 1200 / Math.max(image.width, image.height));

    try {
        for (let resizeAttempt = 0; resizeAttempt < 6; resizeAttempt++) {
            const canvas = document.createElement("canvas");
            canvas.width = Math.max(1, Math.round(image.width * scale));
            canvas.height = Math.max(1, Math.round(image.height * scale));
            const context = canvas.getContext("2d");

            if (!context) break;
            context.drawImage(image, 0, 0, canvas.width, canvas.height);

            for (const quality of [0.9, 0.8, 0.7, 0.6, 0.5]) {
                const blob = await new Promise((resolve) =>
                    canvas.toBlob(resolve, "image/webp", quality),
                );

                if (blob && blob.size <= restaurantLogoMaxBytes) {
                    const extension =
                        blob.type === "image/webp"
                            ? "webp"
                            : blob.type === "image/png"
                              ? "png"
                              : "jpg";

                    return new File([blob], `restaurant-logo.${extension}`, {
                        type: blob.type,
                        lastModified: file.lastModified,
                    });
                }
            }

            scale *= 0.8;
        }
    } finally {
        image.close();
    }

    throw new Error(
        "Logo tidak dapat diperkecil hingga ukuran yang diizinkan.",
    );
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

function getPendingApprovalIds() {
    try {
        const storedIds = JSON.parse(
            sessionStorage.getItem(pendingApprovalStorageKey) ?? "[]",
        );

        return Array.isArray(storedIds)
            ? storedIds.map(Number).filter(Number.isFinite)
            : [];
    } catch {
        return [];
    }
}

function savePendingApprovalIds(ids) {
    sessionStorage.setItem(pendingApprovalStorageKey, JSON.stringify(ids));
}

function addPendingApprovalId(id) {
    const pendingIds = new Set(getPendingApprovalIds());
    pendingIds.add(Number(id));
    savePendingApprovalIds([...pendingIds]);
}

function startUserApprovalPolling() {
    if (userApprovalPollingTimer || !getPendingApprovalIds().length) return;

    userApprovalPollingTimer = window.setInterval(
        pollForUserOrderApprovals,
        5000,
    );
    pollForUserOrderApprovals();
}

async function pollForUserOrderApprovals() {
    if (userApprovalPollInProgress) return;

    const pendingIds = getPendingApprovalIds();
    if (!pendingIds.length) {
        window.clearInterval(userApprovalPollingTimer);
        userApprovalPollingTimer = null;

        return;
    }

    userApprovalPollInProgress = true;

    try {
        const approvedTransactions = [];
        const cancelledTransactions = [];

        for (const transactionId of pendingIds) {
            try {
                const payload = await requestApi(
                    `transaksis/${transactionId}`,
                    {
                        token: window.dapurRapiToken,
                    },
                );
                const transaction = payload.data;

                if (["diproses", "selesai"].includes(transaction.status)) {
                    approvedTransactions.push(transaction);
                } else if (transaction.status === "dibatalkan") {
                    cancelledTransactions.push(transaction);
                }
            } catch (error) {
                if (
                    !/404|tidak ditemukan|menunggu persetujuan/i.test(
                        error.message,
                    )
                ) {
                    throw error;
                }
            }
        }

        const resolvedTransactions = [
            ...approvedTransactions,
            ...cancelledTransactions,
        ];

        if (resolvedTransactions.length) {
            const approvedIds = new Set(
                resolvedTransactions.map((transaction) =>
                    Number(transaction.id),
                ),
            );
            savePendingApprovalIds(
                pendingIds.filter((id) => !approvedIds.has(id)),
            );
            currentTransactionPage = 1;

            const transactionSearch = document.querySelector(
                "[data-transaction-search]",
            );
            if (transactionSearch) transactionSearch.value = "";

            await loadDashboardData(window.dapurRapiToken);
            if (approvedTransactions.length) {
                const approvedLabels = approvedTransactions
                    .map(
                        (transaction) =>
                            `#${String(transaction.id).padStart(4, "0")}`,
                    )
                    .join(", ");
                showDashboardNotice(
                    `Pesanan ${approvedLabels} disetujui admin dan sekarang tersedia di Transaksi.`,
                    "success",
                );
            }

            if (cancelledTransactions.length) {
                const cancelledLabels = cancelledTransactions
                    .map(
                        (transaction) =>
                            `#${String(transaction.id).padStart(4, "0")}`,
                    )
                    .join(", ");
                showDashboardNotice(
                    `Pesanan ${cancelledLabels} dibatalkan oleh pengelola.`,
                );
            }
        }
    } catch {
        // Retry on the next poll if the dashboard is temporarily offline.
    } finally {
        userApprovalPollInProgress = false;
    }
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
            sessionStorage.removeItem(pendingApprovalStorageKey);
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

function renderMinumansTable(drinks, total = drinks.length) {
    const body = document.querySelector("[data-drink-rows]");
    if (!body) return;

    const heading = document.querySelector("[data-drinks-heading]");
    if (heading) heading.textContent = total.toLocaleString("id-ID");

    if (drinks.length === 0) {
        setTableMessage(
            "[data-drink-rows]",
            "Belum ada minuman yang cocok dengan pencarian ini.",
            5,
        );
        return;
    }

    const isAdmin = window.dapurRapiUserRole === "admin";
    body.innerHTML = drinks
        .map((drink) => {
            const actions = isAdmin
                ? `<div class="row-actions"><button class="table-action" type="button" data-drink-edit="${Number(drink.id)}">Ubah</button><button class="table-action table-action-danger" type="button" data-drink-delete="${Number(drink.id)}">Hapus</button></div>`
                : `<button class="table-action table-action-buy" type="button" data-drink-buy="${Number(drink.id)}" ${Number(drink.stok) < 1 ? "disabled" : ""}>${Number(drink.stok) < 1 ? "Habis" : "Beli"}</button>`;

            return `<tr><td>${escapeHtml(drink.nama_minuman)}</td><td>${escapeHtml(drink.deskripsi || "—")}</td><td>${escapeHtml(formatCurrency(drink.harga))}</td><td>${escapeHtml(drink.stok)} unit</td><td>${actions}</td></tr>`;
        })
        .join("");
}

function renderOverview(
    foods,
    drinks,
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
    const lowStockProducts = [
        ...foods
            .filter((food) => Number(food.stok) <= threshold)
            .map((food) => ({
                name: food.nama_makanan,
                category:
                    food.kategori?.nama ??
                    food.nama_kategori ??
                    "Belum dikategorikan",
                price: food.harga,
                stock: food.stok,
            })),
        ...drinks
            .filter((drink) => Number(drink.stok) <= threshold)
            .map((drink) => ({
                name: drink.nama_minuman,
                category: "Minuman",
                price: drink.harga,
                stock: drink.stok,
            })),
    ].sort((first, second) => Number(first.stock) - Number(second.stock));
    const isAdmin = window.dapurRapiUserRole === "admin";
    const metrics = {
        foods: foodTotal,
        categories: categories.length,
        "low-stock": isAdmin
            ? lowStockProducts.length
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
            ? `makanan dan minuman yang perlu dicek`
            : (topSellingFood?.nama_makanan ?? "Belum ada pesanan");

    const lowStockAlert = document.querySelector("[data-low-stock-alert]");
    lowStockAlert.hidden = !isAdmin || lowStockProducts.length === 0;
    if (isAdmin && lowStockProducts.length > 0) {
        document.querySelector("[data-low-stock-alert-text]").textContent =
            `${lowStockProducts.length} makanan/minuman memiliki stok ${threshold} atau kurang.`;
    }

    const lowStockBody = document.querySelector("[data-low-stock-rows]");
    if (lowStockBody) {
        if (lowStockProducts.length === 0) {
            setTableMessage(
                "[data-low-stock-rows]",
                "Semua stok terlihat aman. Tidak ada yang perlu direstok.",
                5,
            );
        } else {
            lowStockBody.innerHTML = lowStockProducts
                .slice(0, 5)
                .map(
                    (product) => `
				<tr>
                    <td>${escapeHtml(product.name)}</td>
                    <td>${escapeHtml(product.category)}</td>
                    <td>${escapeHtml(formatCurrency(product.price))}</td>
                    <td>${escapeHtml(product.stock ?? 0)} unit</td>
					<td><span class="status-pill">Perlu restok</span></td>
				</tr>
			`,
                )
                .join("");
        }
    }

    const recentFoods = [...foods].reverse();
    const foodImages = {
        "nasi pecel":
            "https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d6/Nasi_Pecel_Khas_Blitar.jpg/960px-Nasi_Pecel_Khas_Blitar.jpg",
        "nasi bakar":
            "https://thumb.wikimedia.org/wikipedia/commons/thumb/6/6f/Nasi_Bakar_Ayam.JPG/960px-Nasi_Bakar_Ayam.JPG",
        "sosis solo":
            "https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d2/Sosis_Solo.jpg/960px-Sosis_Solo.jpg",
    };
    const foodImageCredits = {
        "nasi pecel": {
            author: "Supardisahabu",
            license: "CC BY-SA 4.0",
            url: "https://commons.wikimedia.org/wiki/File:Nasi_Pecel_Khas_Blitar.jpg",
        },
        "nasi bakar": {
            author: "Gunawan Kartapranata",
            license: "CC BY-SA 3.0",
            url: "https://commons.wikimedia.org/wiki/File:Nasi_Bakar_Ayam.JPG",
        },
        "sosis solo": {
            author: "Aininapple",
            license: "CC BY-SA 4.0",
            url: "https://commons.wikimedia.org/wiki/File:Sosis_Solo.jpg",
        },
    };
    const recentContainer = document.querySelector("[data-recent-foods]");
    if (recentContainer) {
        recentCarouselPage = 0;
        recentContainer.scrollLeft = 0;
        recentContainer.innerHTML = recentFoods.length
            ? recentFoods
                  .map((food) => {
                      const foodName = food.nama_makanan
                          .trim()
                          .toLocaleLowerCase("id-ID");
                      const foodImage =
                          foodImages[foodName] ??
                          "https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=520&h=260&q=78";
                      const imageCredit = foodImageCredits[foodName];
                      const imageCreditMarkup = imageCredit
                          ? `<a class="recent-photo-credit" href="${imageCredit.url}" target="_blank" rel="noopener noreferrer" title="Foto oleh ${escapeHtml(imageCredit.author)}, ${imageCredit.license}" aria-label="Kredit foto oleh ${escapeHtml(imageCredit.author)}, ${imageCredit.license}">i</a>`
                          : '<a class="recent-photo-credit" data-food-image-credit hidden target="_blank" rel="noopener noreferrer">i</a>';

                      return `
			<article class="recent-card">
                <div class="recent-photo-wrap"><div class="recent-photo" data-food-id="${escapeHtml(food.id)}" role="img" aria-label="Foto ${escapeHtml(food.nama_makanan)}" style="background-image:url('${foodImage}')"></div>${imageCreditMarkup}</div>
				<div class="recent-copy"><strong>${escapeHtml(food.nama_makanan)}</strong><span>${escapeHtml(food.kategori?.nama ?? food.nama_kategori ?? "Menu dapur")}</span></div>
			</article>
		`;
                  })
                  .join("")
            : '<div class="empty-inline">Belum ada menu. Daftar makananmu akan muncul di sini.</div>';
        updateRecentCarouselControls();

        recentFoods.forEach((food) => {
            const foodName = food.nama_makanan
                .trim()
                .toLocaleLowerCase("id-ID");

            if (foodImages[foodName]) return;

            searchCommonsFoodImage(foodName).then((image) => {
                if (!image) return;

                const imageElement = recentContainer.querySelector(
                    `[data-food-id="${Number(food.id)}"]`,
                );
                if (!imageElement) return;

                const creditLink = imageElement.nextElementSibling;
                if (!(creditLink instanceof HTMLAnchorElement)) return;

                imageElement.style.backgroundImage = `url("${image.url}")`;
                creditLink.href = image.credit.url;
                creditLink.title = `Foto oleh ${image.credit.author}, ${image.credit.license}`;
                creditLink.setAttribute(
                    "aria-label",
                    `Kredit foto oleh ${image.credit.author}, ${image.credit.license}`,
                );
                creditLink.hidden = false;
            });
        });
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

    const visibleTransactions = isAdmin
        ? transactions
        : [...transactions].reverse();

    body.innerHTML = visibleTransactions
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
            const isPinnedPaid = isAdmin && payment?.status === "paid";
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
            const statusDisplay =
                isAdmin && status === "pending"
                    ? `<button class="table-action transaction-approve-button" type="button" data-approve-transaction="${Number(transaction.id)}" aria-label="Setujui transaksi ${escapeHtml(String(transaction.id))}">Oke</button>`
                    : isAdmin
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
				<td><span>#${escapeHtml(String(transaction.id).padStart(4, "0"))}</span>${isPinnedPaid ? '<span class="transaction-pinned-label" title="Pembayaran lunas disematkan di bagian atas">Disematkan</span>' : ""}</td>
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

function updateAdminOrderNotificationVisibility() {
    const notifications = document.querySelector(
        "[data-admin-order-notifications]",
    );
    if (notifications) {
        notifications.hidden = notifications.childElementCount === 0;
    }
}

function showNewOrderNotification(transaction) {
    const notifications = document.querySelector(
        "[data-admin-order-notifications]",
    );
    if (!notifications) return;

    const transactionId = Number(transaction.id);
    if (
        notifications.querySelector(
            `[data-admin-order-notice="${transactionId}"]`,
        )
    ) {
        return;
    }

    const foodSummary = (transaction.items ?? [])
        .map((item) => `${item.nama_makanan ?? "Menu"} × ${item.qty}`)
        .join(", ");
    const notice = document.createElement("article");
    notice.className = "admin-order-notice";
    notice.dataset.adminOrderNotice = String(transactionId);
    notice.innerHTML = `
        <div class="admin-order-notice-heading">
            <div class="admin-order-notice-copy">
                <strong>Pesanan baru #${escapeHtml(String(transactionId).padStart(4, "0"))}</strong>
                <span>${escapeHtml(transaction.user?.name ?? "Pengguna")} · ${escapeHtml(formatCurrency(transaction.total_harga))}</span>
            </div>
            <button class="admin-order-notice-dismiss" type="button" data-dismiss-order-notice aria-label="Tutup pemberitahuan">×</button>
        </div>
        <p class="admin-order-notice-items">${escapeHtml(foodSummary || "Rincian makanan tidak tersedia")}</p>
        <div class="admin-order-notice-actions"><button class="table-action transaction-approve-button" type="button" data-approve-transaction="${transactionId}">Oke</button></div>
    `;
    notifications.append(notice);
    notifications.hidden = false;
}

async function pollForNewAdminOrders() {
    try {
        const payload = await requestApi("transaksis?page=1", {
            token: window.dapurRapiToken,
        });
        const transactions = unwrapData(payload);
        const newTransactions = transactions
            .filter(
                (transaction) =>
                    Number(transaction.id) > latestKnownAdminTransactionId,
            )
            .sort((first, second) => Number(first.id) - Number(second.id));

        for (const transaction of newTransactions) {
            if (transaction.status === "pending") {
                showNewOrderNotification(transaction);
            }

            latestKnownAdminTransactionId = Math.max(
                latestKnownAdminTransactionId,
                Number(transaction.id),
            );
        }
    } catch {
        // Leave the dashboard available when a background check briefly fails.
    }
}

async function approvePendingTransaction(transactionId, button) {
    button.disabled = true;

    try {
        await requestApi(`transaksis/${transactionId}/status`, {
            method: "PUT",
            token: window.dapurRapiToken,
            body: JSON.stringify({ status: "diproses" }),
        });
        document
            .querySelector(`[data-admin-order-notice="${transactionId}"]`)
            ?.remove();
        updateAdminOrderNotificationVisibility();
        showDashboardNotice(
            "Pesanan disetujui dan sedang diproses.",
            "success",
        );
        await loadDashboardData(window.dapurRapiToken);
    } catch (error) {
        button.disabled = false;
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

function renderRestaurantProfile(profile) {
    window.dapurRapiRestaurantProfile = profile;

    document.querySelectorAll("[data-restaurant-name]").forEach((element) => {
        element.textContent = profile.name;
    });

    document.querySelectorAll("[data-brand-logo]").forEach((image) => {
        image.hidden = !profile.logo_url;
        image.src = profile.logo_url ?? "";
        image.previousElementSibling.hidden = Boolean(profile.logo_url);
    });

    const viewName = document.querySelector("[data-restaurant-view-name]");
    const viewHistory = document.querySelector(
        "[data-restaurant-view-history]",
    );
    const viewLogo = document.querySelector("[data-restaurant-view-logo]");
    const viewLogoMark = document.querySelector("[data-profile-logo-mark]");
    const editorLogo = document.querySelector("[data-restaurant-editor-logo]");
    const editorLogoMark = document.querySelector("[data-editor-logo-mark]");

    if (viewName) viewName.textContent = profile.name;
    if (viewHistory) {
        viewHistory.textContent =
            profile.history?.trim() || "Sejarah restoran belum ditambahkan.";
    }
    if (viewLogo) {
        viewLogo.hidden = !profile.logo_url;
        viewLogo.src = profile.logo_url ?? "";
        viewLogoMark.hidden = Boolean(profile.logo_url);
    }
    if (editorLogo) {
        editorLogo.hidden = !profile.logo_url;
        editorLogo.src = profile.logo_url ?? "";
        editorLogoMark.hidden = Boolean(profile.logo_url);
    }
}

function openRestaurantDialog() {
    const dialog = document.querySelector("[data-restaurant-dialog]");
    const viewer = document.querySelector("[data-restaurant-profile-view]");
    const form = document.querySelector("[data-restaurant-form]");
    const isAdmin = window.dapurRapiUserRole === "admin";

    viewer.hidden = isAdmin;
    form.hidden = !isAdmin;

    if (isAdmin) {
        form.reset();
        document.querySelector("[data-restaurant-name-input]").value =
            window.dapurRapiRestaurantProfile.name;
        document.querySelector("[data-restaurant-history-input]").value =
            window.dapurRapiRestaurantProfile.history ?? "";
        renderRestaurantProfile(window.dapurRapiRestaurantProfile);
    }

    dialog.showModal();
}

async function loadDashboardData(token) {
    const [
        profilePayload,
        foodPayload,
        drinkPayload,
        categoryPayload,
        transactionPayload,
        restaurantProfilePayload,
    ] = await Promise.all([
        requestApi("me", { token }),
        requestApi("makanans", { token }),
        requestApi("minumans", { token }),
        requestApi("kategoris", { token }),
        requestApi(transactionRequestPath(currentTransactionPage), {
            token,
        }),
        requestApi("restaurant-profile", { token }),
    ]);
    const profile = profilePayload.data;
    const foods = unwrapData(foodPayload);
    const drinks = unwrapData(drinkPayload);
    const categories = unwrapData(categoryPayload);
    const transactions = unwrapData(transactionPayload);
    const foodTotal = collectionTotal(foodPayload, foods);
    const transactionTotal = Number(
        transactionPayload.active_transactions_count ??
            collectionTotal(transactionPayload, transactions),
    );

    window.dapurRapiFoods = foods;
    window.dapurRapiDrinks = drinks;
    window.dapurRapiCategories = categories;
    window.dapurRapiUserRole = profile.role === "admin" ? "admin" : "user";
    renderRestaurantProfile(restaurantProfilePayload.data);
    if (window.dapurRapiUserRole === "admin" && !adminOrderWatchInitialized) {
        latestKnownAdminTransactionId = Math.max(
            0,
            ...transactions.map((transaction) => Number(transaction.id)),
        );
        transactions
            .filter((transaction) => transaction.status === "pending")
            .forEach(showNewOrderNotification);
        adminOrderWatchInitialized = true;
    }
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
    document.querySelector("[data-create-drink]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector("[data-create-category]").hidden =
        window.dapurRapiUserRole !== "admin";
    document.querySelector('[data-count="foods"]').textContent =
        foodTotal.toLocaleString("id-ID");
    document.querySelector('[data-count="drinks"]').textContent =
        collectionTotal(drinkPayload, drinks).toLocaleString("id-ID");
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
        drinks,
        categories,
        transactions,
        foodTotal + collectionTotal(drinkPayload, drinks),
        transactionTotal,
        transactionPayload.top_selling_food,
    );
    renderFoodsTable(foods, foodTotal);
    renderMinumansTable(drinks, collectionTotal(drinkPayload, drinks));
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
    form.querySelector('[name="id"]').value = food?.id ?? "";
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

function openDrinkDialog(drink = null) {
    const form = document.querySelector("[data-drink-form]");
    form.reset();
    form.querySelector('[name="id"]').value = drink?.id ?? "";
    document.querySelector("[data-drink-dialog-title]").textContent = drink
        ? "Ubah minuman"
        : "Tambah minuman";
    form.querySelector('[name="nama_minuman"]').value =
        drink?.nama_minuman ?? "";
    form.querySelector('[name="harga"]').value = drink?.harga ?? "";
    form.querySelector('[name="stok"]').value = drink?.stok ?? "";
    form.querySelector('[name="deskripsi"]').value = drink?.deskripsi ?? "";
    document.querySelector("[data-drink-dialog]").showModal();
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
    const product = getSelectedPurchaseProduct();
    const quantity = Number(document.querySelector('[name="qty"]').value);
    if (!product) return;

    const total = Number(product.harga) * quantity;
    document.querySelector("[data-payment-item-name]").textContent =
        product.nama_makanan ?? product.nama_minuman;
    document.querySelector("[data-payment-item-quantity]").textContent =
        `${quantity} × ${formatCurrency(product.harga)}`;
    document.querySelector("[data-payment-total]").textContent =
        formatCurrency(total);
    updatePaymentInstructions();
}

function getSelectedPurchaseProduct() {
    const drinkId = Number(
        document.querySelector("[data-purchase-minuman-id]").value,
    );

    if (drinkId) {
        return window.dapurRapiDrinks.find(
            (drink) => Number(drink.id) === drinkId,
        );
    }

    const foodId = Number(document.querySelector("[data-purchase-id]").value);

    return window.dapurRapiFoods.find((food) => Number(food.id) === foodId);
}

function openPurchaseDialog(product, isDrink = false) {
    document.querySelector("[data-purchase-id]").value = isDrink
        ? ""
        : product.id;
    document.querySelector("[data-purchase-minuman-id]").value = isDrink
        ? product.id
        : "";
    document.querySelector("[data-purchase-name]").textContent =
        product.nama_makanan ?? product.nama_minuman;
    document.querySelector("[data-purchase-price]").textContent =
        `${formatCurrency(product.harga)} per porsi · stok ${product.stok} unit`;

    const quantity = document.querySelector('[name="qty"]');
    quantity.max = product.stok;
    quantity.value = "1";

    const paymentSettings = JSON.parse(
        document.querySelector("[data-payment-settings]").textContent,
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
    document.querySelectorAll("[data-restaurant-brand]").forEach((brand) => {
        brand.addEventListener("click", (event) => {
            event.preventDefault();
            openRestaurantDialog();
        });
    });

    const logoLightbox = document.querySelector("[data-logo-lightbox]");
    const largeLogo = document.querySelector("[data-logo-lightbox-image]");
    const openLogo = (image) => {
        if (!image.src) return;

        largeLogo.src = image.currentSrc || image.src;
        largeLogo.alt = image.alt;
        logoLightbox.showModal();
    };

    document.querySelectorAll("[data-logo-zoom]").forEach((image) => {
        image.addEventListener("click", () => openLogo(image));
        image.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openLogo(image);
            }
        });
    });

    document
        .querySelector("[data-logo-lightbox-close]")
        ?.addEventListener("click", () => logoLightbox.close());
    logoLightbox?.addEventListener("click", (event) => {
        if (event.target === logoLightbox) logoLightbox.close();
    });

    document
        .querySelector("[data-restaurant-logo-input]")
        ?.addEventListener("change", (event) => {
            const file = event.currentTarget.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.addEventListener("load", () => {
                const preview = document.querySelector(
                    "[data-restaurant-editor-logo]",
                );
                preview.src = reader.result;
                preview.hidden = false;
                document.querySelector("[data-editor-logo-mark]").hidden = true;
            });
            reader.readAsDataURL(file);
        });

    document
        .querySelector("[data-restaurant-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const error = document.querySelector(
                "[data-restaurant-form-error]",
            );
            const formData = new FormData(form);
            formData.set("name", String(formData.get("name")).trim());
            formData.set(
                "history",
                String(formData.get("history") ?? "").trim(),
            );

            error.hidden = true;
            button.disabled = true;

            try {
                const selectedLogo = formData.get("logo");
                if (
                    selectedLogo instanceof File &&
                    selectedLogo.size > restaurantLogoMaxBytes
                ) {
                    formData.set(
                        "logo",
                        await optimizeRestaurantLogo(selectedLogo),
                    );
                }

                const payload = await requestApi("restaurant-profile", {
                    method: "POST",
                    token: window.dapurRapiToken,
                    body: formData,
                });
                renderRestaurantProfile(payload.data);
                document.querySelector("[data-restaurant-dialog]").close();
                showDashboardNotice(payload.message, "success");
            } catch (requestError) {
                error.textContent = requestError.message;
                error.hidden = false;
            } finally {
                button.disabled = false;
            }
        });

    document.querySelector("[data-recent-foods]")?.addEventListener(
        "scroll",
        () => {
            const grid = document.querySelector("[data-recent-foods]");
            const pageCount = Math.ceil(
                grid.querySelectorAll(".recent-card").length / 4,
            );
            const maxScroll = grid.scrollWidth - grid.clientWidth;

            if (pageCount > 1 && maxScroll > 0) {
                recentCarouselPage = Math.round(
                    (grid.scrollLeft / maxScroll) * (pageCount - 1),
                );
            }

            updateRecentCarouselControls();
        },
        { passive: true },
    );
    document
        .querySelector("[data-recent-slider]")
        ?.addEventListener("input", (event) => {
            moveRecentCarouselToPage(Number(event.currentTarget.value));
        });

    if (!document.querySelector("[data-dashboard]")) return;

    initializePaymentMethods();
    document
        .querySelector("[data-admin-order-notifications]")
        ?.addEventListener("click", async (event) => {
            const dismissButton = event.target.closest(
                "[data-dismiss-order-notice]",
            );
            if (dismissButton) {
                dismissButton.closest("[data-admin-order-notice]")?.remove();
                updateAdminOrderNotificationVisibility();
                return;
            }

            const approveButton = event.target.closest(
                "[data-approve-transaction]",
            );
            if (approveButton) {
                await approvePendingTransaction(
                    Number(approveButton.dataset.approveTransaction),
                    approveButton,
                );
            }
        });
    document
        .querySelector("[data-transaction-rows]")
        ?.addEventListener("click", async (event) => {
            const approveButton = event.target.closest(
                "[data-approve-transaction]",
            );
            if (approveButton) {
                await approvePendingTransaction(
                    Number(approveButton.dataset.approveTransaction),
                    approveButton,
                );
            }
        });
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
        .querySelector("[data-create-drink]")
        ?.addEventListener("click", () => openDrinkDialog());
    document
        .querySelector("[data-create-category]")
        ?.addEventListener("click", () => openCategoryDialog());

    let drinkSearchTimeout;
    document
        .querySelector("[data-drink-search]")
        ?.addEventListener("input", (event) => {
            const query = event.currentTarget.value.trim();
            window.clearTimeout(drinkSearchTimeout);
            drinkSearchTimeout = window.setTimeout(async () => {
                try {
                    const payload = await requestApi(
                        `minumans?search=${encodeURIComponent(query)}`,
                        { token: window.dapurRapiToken },
                    );
                    const drinks = unwrapData(payload);
                    window.dapurRapiDrinks = drinks;
                    renderMinumansTable(
                        drinks,
                        collectionTotal(payload, drinks),
                    );
                } catch (error) {
                    showDashboardNotice(error.message);
                }
            }, 250);
        });

    document
        .querySelector("[data-drink-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const values = Object.fromEntries(new FormData(form));
            const drinkId = values.id;
            const payload = {
                nama_minuman: values.nama_minuman,
                harga: Number(values.harga),
                stok: Number(values.stok),
                deskripsi: values.deskripsi,
            };

            button.disabled = true;
            try {
                await requestApi(drinkId ? `minumans/${drinkId}` : "minumans", {
                    method: drinkId ? "PUT" : "POST",
                    token: window.dapurRapiToken,
                    body: JSON.stringify(payload),
                });
                document.querySelector("[data-drink-dialog]").close();
                showDashboardNotice(
                    drinkId
                        ? "Perubahan minuman tersimpan."
                        : "Minuman berhasil ditambahkan ke tabel terpisah.",
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
        .querySelector("[data-drink-rows]")
        ?.addEventListener("click", async (event) => {
            const button = event.target.closest("button");
            if (!button) return;

            const drinkId = Number(
                button.dataset.drinkEdit ??
                    button.dataset.drinkDelete ??
                    button.dataset.drinkBuy,
            );
            const drink = window.dapurRapiDrinks.find(
                (item) => Number(item.id) === drinkId,
            );
            if (!drink) return;

            if (button.hasAttribute("data-drink-edit")) {
                openDrinkDialog(drink);
                return;
            }

            if (button.hasAttribute("data-drink-delete")) {
                if (
                    !window.confirm(
                        `Hapus ${drink.nama_minuman} dari daftar minuman?`,
                    )
                )
                    return;

                try {
                    await requestApi(`minumans/${drinkId}`, {
                        method: "DELETE",
                        token: window.dapurRapiToken,
                    });
                    showDashboardNotice("Minuman berhasil dihapus.", "success");
                    await loadDashboardData(window.dapurRapiToken);
                } catch (error) {
                    showDashboardNotice(error.message);
                }
                return;
            }

            if (button.hasAttribute("data-drink-buy")) {
                openPurchaseDialog(drink, true);
            }
        });

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
                openPurchaseDialog(food);
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
            const product = getSelectedPurchaseProduct();

            if (!product || quantity < 1 || quantity > Number(product.stok)) {
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
        ?.addEventListener("click", async (event) => {
            const approveButton = event.target.closest(
                "[data-transaction-approve]",
            );
            if (!approveButton) return;

            const transactionId = Number(
                approveButton.dataset.transactionApprove,
            );
            approveButton.disabled = true;

            try {
                await requestApi(`transaksis/${transactionId}/status`, {
                    method: "PUT",
                    token: window.dapurRapiToken,
                    body: JSON.stringify({ status: "diproses" }),
                });
                showDashboardNotice(
                    "Pesanan disetujui dan sedang diproses.",
                    "success",
                );
                await loadDashboardData(window.dapurRapiToken);
            } catch (error) {
                approveButton.disabled = false;
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
            const drinkId = Number(
                document.querySelector("[data-purchase-minuman-id]").value,
            );
            const quantity = Number(
                document.querySelector('[name="qty"]').value,
            );
            const product = getSelectedPurchaseProduct();

            if (!product || quantity < 1 || quantity > Number(product.stok)) {
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
                    const purchasePayload = await requestApi("transaksis", {
                        method: "POST",
                        token: window.dapurRapiToken,
                        body: JSON.stringify({
                            items: [
                                {
                                    ...(drinkId
                                        ? { minuman_id: drinkId }
                                        : { makanan_id: foodId }),
                                    qty: quantity,
                                },
                            ],
                            payment_method: paymentMethod,
                        }),
                    });
                    addPendingApprovalId(purchasePayload.data.id);
                    document.querySelector("[data-purchase-dialog]").close();
                    showDashboardNotice(
                        "Pesanan berhasil dikirim. Menunggu persetujuan admin sebelum muncul di transaksi.",
                        "order-confirmation",
                    );
                    await loadDashboardData(window.dapurRapiToken);
                    startUserApprovalPolling();
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
        if (window.dapurRapiUserRole === "admin" && !adminOrderPollingTimer) {
            adminOrderPollingTimer = window.setInterval(
                pollForNewAdminOrders,
                8000,
            );
        } else if (window.dapurRapiUserRole !== "admin") {
            startUserApprovalPolling();
        }
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
