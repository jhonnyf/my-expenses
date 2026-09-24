@extends('layout.main-public')

@section('page-title', 'Política de Privacidade')

@section('content')
    <p class="text-xs text-muted-foreground">Última atualização: {{ \Illuminate\Support\Carbon::parse(config('legal.current_terms_version'))->translatedFormat('d \d\e F \d\e Y') }}</p>

    <section>
        <h2>1. Controlador dos Dados</h2>
        <p>
            Esta Política de Privacidade descreve como o {{ env('APP_NAME') }} trata os dados pessoais dos
            seus usuários, em conformidade com a Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018
            — LGPD). O {{ env('APP_NAME') }} atua como controlador dos dados pessoais coletados por meio da
            plataforma. O serviço é operado por <strong>[RAZÃO SOCIAL A DEFINIR — CNPJ A DEFINIR]</strong>.
        </p>
    </section>

    <section>
        <h2>2. Encarregado de Dados (DPO)</h2>
        <p>
            O encarregado pelo tratamento de dados pessoais (Data Protection Officer) pode ser contatado
            pelo e-mail <a class="link" href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>
            para esclarecer dúvidas, receber reclamações ou processar solicitações relacionadas aos seus
            dados pessoais.
        </p>
    </section>

    <section>
        <h2>3. Dados Coletados</h2>
        <p>Coletamos os seguintes dados:</p>
        <ul>
            <li>Dados de cadastro: nome, e-mail, senha (armazenada de forma criptografada), cidade e estado (opcionais);</li>
            <li>
                Dados das notas fiscais (NFC-e) que você importa: emitente, produtos, preços, forma de
                pagamento e o XML bruto da nota, que pode conter o CPF do consumidor caso ele tenha sido
                informado no momento da compra;
            </li>
            <li>
                Dados gerados pelo seu uso do serviço: categorias, orçamentos, listas de compras, apelidos de
                lojas, produtos ocultados nas compras recorrentes, agendamento de relatórios por e-mail e
                notificações (alertas de orçamento e de queda de preço);
            </li>
            <li>Dados técnicos de uso da plataforma, para fins de segurança e melhoria do serviço.</li>
        </ul>
    </section>

    <section>
        <h2>4. Finalidade do Tratamento</h2>
        <p>
            Utilizamos seus dados para viabilizar o funcionamento do serviço (importação e organização de
            notas fiscais, orçamentos, categorização automática, listas de compras, compras recorrentes), para gerar o histórico
            agregado de preços compartilhado entre usuários, para enviar, a seu pedido, relatórios por e-mail (inclusive de forma recorrente), para
            emitir alertas de orçamento e de queda de preço, e para comunicações relacionadas à sua conta.
        </p>
    </section>

    <section>
        <h2>5. Base Legal</h2>
        <p>
            O tratamento de dados de cadastro e uso da plataforma se baseia na execução do contrato firmado
            com você ao aceitar estes termos. O compartilhamento agregado de dados de produtos e preços se
            baseia no seu consentimento, dado no momento em que você importa uma nota fiscal, conforme
            descrito nos Termos de Uso.
        </p>
    </section>

    <section>
        <h2>6. Compartilhamento com Terceiros e Operadores</h2>
        <p>Para viabilizar o serviço, alguns dados são compartilhados com os seguintes operadores de tratamento:</p>
        <ul>
            <li>
                <strong>Google, Facebook e Apple</strong> — quando você opta por entrar com sua conta social,
                recebemos seu nome, e-mail e um identificador da conta para autenticação;
            </li>
            <li>
                <strong>OpenStreetMap (Nominatim)</strong> — para sugerir sua cidade/estado ou identificar sua
                localização a partir de coordenadas de GPS, quando você opta por usar essa funcionalidade;
            </li>
            <li>
                <strong>Google Gemini</strong> — para sugerir e aplicar automaticamente nomes de produtos e
                categorias a partir das descrições das notas fiscais importadas e dos nomes das suas
                categorias (dados de produto e de organização, sem CPF, nome ou e-mail).
            </li>
        </ul>
        <p>Não compartilhamos seus dados com terceiros para fins de publicidade ou venda de dados.</p>
    </section>

    <section>
        <h2>7. Retenção de Dados e Exclusão de Conta</h2>
        <p>
            Mantemos seus dados enquanto sua conta estiver ativa. Você pode excluir sua conta a qualquer
            momento na tela "Minha Conta" &rarr; "Segurança". Ao excluir a conta:
        </p>
        <ul>
            <li>Seus dados de cadastro, perfil (incluindo CPF/CNPJ), foto, assinatura, notificações, orçamentos e listas de compras são removidos permanentemente;</li>
            <li>
                Suas notas fiscais já importadas são <strong>anonimizadas</strong> (o vínculo com sua conta é
                removido), mas os dados de produto/preço/emitente são preservados para manter o histórico
                agregado de preços que beneficia a comunidade de usuários, conforme descrito na seção 8.
            </li>
        </ul>
    </section>

    <section>
        <h2>8. Compartilhamento e Pseudonimização de Dados de Compra</h2>
        <p>
            Os dados de produto, preço e emitente das suas notas fiscais são exibidos a outros usuários de
            forma agregada, sem qualquer identificação de quem realizou a compra.
        </p>
        <p>
            É importante ser preciso quanto à natureza técnica desse tratamento: enquanto sua conta estiver
            ativa, o registro do preço permanece associado ao seu usuário no banco de dados, mesmo que essa
            associação não seja exibida publicamente. Isso caracteriza <strong>pseudonimização</strong> — o
            dado pode, em tese, ser revertido à sua identidade pelo controlador — e não uma anonimização
            irreversível. O {{ env('APP_NAME') }} não expõe essa associação a outros usuários em nenhuma
            circunstância normal de uso da plataforma. Após a exclusão da conta (seção 7), esse vínculo é
            removido e o dado passa a ser efetivamente anônimo.
        </p>
    </section>

    <section>
        <h2>9. Cookies</h2>
        <p>
            Utilizamos apenas cookies essenciais de sessão, necessários para manter você autenticado na
            plataforma. Não utilizamos cookies de terceiros, publicidade ou rastreamento de navegação entre
            sites.
        </p>
    </section>

    <section>
        <h2>10. Direitos do Titular</h2>
        <p>Nos termos da LGPD, você tem direito a:</p>
        <ul>
            <li>Confirmar a existência de tratamento e acessar seus dados;</li>
            <li>Corrigir dados incompletos, inexatos ou desatualizados;</li>
            <li>Solicitar a exclusão ou anonimização de dados desnecessários ou excessivos;</li>
            <li>Solicitar a portabilidade dos seus dados a outro fornecedor de serviço;</li>
            <li>Revogar o consentimento e se opor a tratamentos realizados com base nele.</li>
        </ul>
    </section>

    <section>
        <h2>11. Como Exercer seus Direitos</h2>
        <p>
            Você pode exercer os direitos de acesso, exclusão de conta e portabilidade diretamente na tela
            "Minha Conta" &rarr; "Segurança", nas opções "Exportar meus dados" e "Excluir minha conta". Para
            os demais direitos, ou em caso de dúvida, entre em contato pelo e-mail
            <a class="link" href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.
        </p>
    </section>

    <section>
        <h2>12. Segurança da Informação</h2>
        <p>
            Adotamos medidas técnicas e organizacionais para proteger seus dados contra acesso não
            autorizado, perda, alteração ou destruição indevida, incluindo criptografia de senhas e de
            documentos pessoais (CPF/CNPJ) armazenados.
        </p>
    </section>

    <section>
        <h2>13. Contato do Controlador</h2>
        <p>
            Em caso de dúvidas sobre esta Política ou sobre o tratamento dos seus dados pessoais, entre em
            contato pelo e-mail <a class="link" href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.
        </p>
    </section>

    <section>
        <h2>14. Alterações desta Política</h2>
        <p>
            Esta Política pode ser atualizada periodicamente. Mudanças relevantes serão comunicadas por
            e-mail ou por aviso na plataforma, e poderão exigir que você aceite novamente os termos vigentes
            no seu próximo acesso.
        </p>
    </section>
@endsection
