@extends('layout.main-public')

@section('page-title', 'Termos de Uso')

@section('content')
    <p class="text-xs text-muted-foreground">Última atualização: {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>

    <section>
        <h2>1. Sobre o Serviço</h2>
        <p>
            O {{ env('APP_NAME') }} é um serviço que ajuda você a controlar seus gastos pessoais a partir da
            importação de Notas Fiscais de Consumidor Eletrônica (NFC-e), organizando produtos, preços,
            categorias, orçamentos e listas de compras.
        </p>
    </section>

    <section>
        <h2>2. Elegibilidade e Cadastro</h2>
        <p>
            Para usar o serviço, você precisa criar uma conta informando dados verdadeiros e completos.
            Você é responsável por manter essas informações atualizadas e por qualquer atividade realizada
            na sua conta.
        </p>
    </section>

    <section>
        <h2>3. Conta do Usuário e Segurança de Acesso</h2>
        <p>
            Você é responsável por manter a confidencialidade da sua senha e de qualquer credencial de acesso.
            Notifique-nos imediatamente em caso de uso não autorizado da sua conta.
        </p>
    </section>

    <section>
        <h2>4. Consentimento para Agregação de Dados de Produtos e Preços</h2>
        <p>
            Ao importar uma NFC-e no {{ env('APP_NAME') }}, você concorda expressamente que os dados de
            <strong>produto, preço pago, emitente (loja) e data da compra</strong> contidos nessa nota sejam
            agregados e exibidos a outros usuários da plataforma, para fins de histórico de preços e
            comparação entre lojas.
        </p>
        <p>
            <strong>Sua identidade como comprador nunca é exibida a outros usuários.</strong> Nome, CPF,
            e-mail ou qualquer outro identificador que permita saber quem comprou um determinado produto não
            são, em nenhuma hipótese, mostrados para outros usuários — apenas o emitente, o produto, o preço
            e a data da compra ficam visíveis nos dados agregados.
        </p>
        <p>
            O emitente (razão social e CNPJ da loja) é informação pública, constante da própria nota fiscal
            emitida pelo estabelecimento, e pode ser exibido normalmente, inclusive vinculado ao histórico de
            preços dos produtos que ele vende.
        </p>
        <p>
            É proibido utilizar o serviço, suas informações agregadas ou qualquer outro meio para tentar
            identificar ou reidentificar o comprador de um produto específico.
        </p>
    </section>

    <section>
        <h2>5. Uso Aceitável</h2>
        <p>Ao usar o {{ env('APP_NAME') }}, você concorda em não:</p>
        <ul>
            <li>Tentar acessar contas de outros usuários ou dados que não sejam seus;</li>
            <li>Realizar engenharia reversa, scraping ou extração automatizada de dados da plataforma;</li>
            <li>Tentar identificar ou reidentificar outros usuários a partir de dados agregados;</li>
            <li>Utilizar o serviço para fins comerciais não autorizados ou fraudulentos;</li>
            <li>Importar notas fiscais que não sejam suas ou que tenham sido obtidas de forma indevida.</li>
        </ul>
    </section>

    <section>
        <h2>6. Propriedade Intelectual</h2>
        <p>
            A marca, o layout, os textos e os recursos do {{ env('APP_NAME') }} são de propriedade do serviço
            ou de seus licenciantes, sendo protegidos pela legislação de propriedade intelectual aplicável.
        </p>
    </section>

    <section>
        <h2>7. Planos e Cobrança</h2>
        <p>
            O {{ env('APP_NAME') }} oferece um plano gratuito e um plano Pro, com recursos adicionais (como
            exportação de relatórios em CSV). Cobranças, cancelamento e reembolso do plano Pro seguem a
            política de cobrança vigente no momento da contratação, informada antes da confirmação do
            pagamento.
        </p>
    </section>

    <section>
        <h2>8. Limitação de Responsabilidade</h2>
        <p>
            Os preços e informações exibidos podem estar desatualizados ou divergir do praticado no
            estabelecimento. O {{ env('APP_NAME') }} não garante a exatidão de dados de terceiros, incluindo
            informações consultadas junto à SEFAZ. O serviço é fornecido "como está", sem garantias de que
            atenderá a expectativas específicas.
        </p>
    </section>

    <section>
        <h2>9. Rescisão e Encerramento de Conta</h2>
        <p>
            Você pode encerrar sua conta a qualquer momento diretamente pela tela "Minha Conta" &rarr;
            "Segurança", na opção "Excluir minha conta" — veja a
            <a class="link" href="{{ route('legal.privacy') }}">Política de Privacidade</a> para detalhes
            sobre quais dados são removidos e quais são anonimizados. Podemos suspender ou encerrar contas
            que violem estes Termos, mediante notificação, sempre que possível.
        </p>
    </section>

    <section>
        <h2>10. Alterações destes Termos</h2>
        <p>
            Podemos atualizar estes Termos periodicamente. Mudanças relevantes serão comunicadas por e-mail
            ou por aviso na plataforma. O uso continuado do serviço após a atualização implica concordância
            com os novos Termos.
        </p>
    </section>

    <section>
        <h2>11. Legislação Aplicável e Foro</h2>
        <p>
            Estes Termos são regidos pelas leis da República Federativa do Brasil. Fica eleito o foro do
            domicílio do usuário para dirimir eventuais controvérsias, salvo disposição legal em contrário.
        </p>
    </section>

    <section>
        <h2>12. Contato</h2>
        <p>
            Dúvidas sobre estes Termos podem ser enviadas para
            <a class="link" href="mailto:contato@cestazen.com.br">contato@cestazen.com.br</a>.
        </p>
    </section>
@endsection
