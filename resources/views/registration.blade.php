<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Tenant - Teste</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-2xl">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-800">Novo Cadastro de Tenant</h1>

        <div id="error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"
            role="alert">
            <span class="block sm:inline" id="error-text"></span>
        </div>

        <div id="step-1-plans" class="mb-6">
            <h2 class="text-lg font-semibold mb-3 text-gray-700">1. Escolha um Plano</h2>
            <div id="plans-container" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Plans will be loaded here -->
                <p class="text-gray-500">Carregando planos...</p>
            </div>
            <input type="hidden" id="selected-plan" name="plan_slug">
        </div>

        <div id="step-2-form" class="hidden">
            <h2 class="text-lg font-semibold mb-3 text-gray-700">2. Dados da Empresa</h2>
            <form id="signup-form" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="name">Nome da Empresa</label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="name" type="text" placeholder="Ex: Minha Empresa Ltda" required>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="domain">Domínio (Slug)</label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="domain" type="text" placeholder="minhaempresa" required>
                    <p class="text-xs text-gray-500 mt-1">Seu sistema será: <span
                            id="domain-preview">...</span>.sigpro.com.br</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="admin_name">Nome do
                        Administrador</label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="admin_name" type="text" placeholder="Seu Nome Completo" required>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email do Admin</label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="email" type="email" placeholder="admin@empresa.com" required>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Senha</label>
                    <input
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        id="password" type="password" placeholder="********" required>
                </div>

                <div class="flex items-center justify-between mt-6">
                    <button id="submit-btn"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full transition duration-300"
                        type="submit">
                        Ir para Checkout
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const apiUrl = '/api/v1';

        // Load Plans on Init
        async function loadPlans() {
            try {
                const response = await fetch(`${apiUrl}/plans`);
                const data = await response.json();

                const container = document.getElementById('plans-container');
                container.innerHTML = '';

                if (data.data && data.data.length > 0) {
                    data.data.forEach(plan => {
                        const card = document.createElement('div');
                        card.className = 'plan-card border rounded p-4 cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition';
                        card.dataset.slug = plan.slug;
                        card.onclick = () => selectPlan(plan.slug, card);

                        card.innerHTML = `
                            <h3 class="font-bold text-lg">${plan.name}</h3>
                            <p class="text-gray-600 text-sm mb-2">${plan.description || ''}</p>
                            <div class="text-xl font-bold text-blue-600">R$ ${plan.price} <span class="text-sm text-gray-500">/${plan.interval === 'month' ? 'mês' : 'ano'}</span></div>
                        `;
                        container.appendChild(card);
                    });
                } else {
                    container.innerHTML = '<p class="text-red-500">Nenhum plano encontrado.</p>';
                }
            } catch (error) {
                console.error('Error loading plans:', error);
                document.getElementById('plans-container').innerHTML = '<p class="text-red-500">Erro ao carregar planos.</p>';
            }
        }

        function selectPlan(slug, cardElement) {
            document.getElementById('selected-plan').value = slug;
            document.getElementById('step-2-form').classList.remove('hidden');

            // Visual feedback
            document.querySelectorAll('.plan-card').forEach(c => {
                c.classList.remove('border-blue-500', 'bg-blue-50', 'ring-2', 'ring-blue-500');
            });
            cardElement.classList.add('border-blue-500', 'bg-blue-50', 'ring-2', 'ring-blue-500');
        }

        // Domain preview logic
        document.getElementById('domain').addEventListener('input', function (e) {
            // Slugify: lowercase, remove special chars, replace spaces with dashes
            let val = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/\s+/g, '-');
            e.target.value = val;
            document.getElementById('domain-preview').innerText = val || '...';
        });

        // Form Submit
        document.getElementById('signup-form').addEventListener('submit', async function (e) {
            e.preventDefault();

            const btn = document.getElementById('submit-btn');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'Processando...';
            document.getElementById('error-message').classList.add('hidden');

            // Construct payload matching SignupRequest expectations
            const payload = {
                plan_slug: document.getElementById('selected-plan').value,
                organization_name: document.getElementById('name').value,
                slug: document.getElementById('domain').value,
                admin_name: document.getElementById('admin_name').value,
                admin_email: document.getElementById('email').value,
                admin_password: document.getElementById('password').value
            };

            try {
                const response = await fetch(`${apiUrl}/signup`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    // Redirect to Stripe
                    window.location.href = result.data.checkout_url;
                } else {
                    console.error('Signup Error Response:', result);
                    let msg = result.message || 'Erro ao processar cadastro.';

                    // Handle Validation Errors from various formats
                    const details = result.error?.details || result.errors;

                    if (details) {
                        if (typeof details === 'object') {
                            // Join array messages
                            const validations = Object.values(details).flat().join('\n');
                            msg += '\n\n' + validations;
                        } else {
                            msg += ' ' + JSON.stringify(details);
                        }
                    } else if (result.error && result.error.message) {
                        msg = result.error.message;
                    }

                    throw new Error(msg);
                }

            } catch (error) {
                console.error(error);
                const errorDiv = document.getElementById('error-message');
                // Use innerText to allow newlines
                document.getElementById('error-text').innerText = error.message;
                errorDiv.classList.remove('hidden');
                btn.disabled = false;
                btn.innerText = originalText;
            }
        });

        window.onload = loadPlans;
    </script>
</body>

</html>