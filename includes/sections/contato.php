<section class="contato" id="contato">

    <div class="container">

        <div class="contato-header">

            <span class="section-tag">
                FALE CONOSCO
            </span>

            <h2 class="section-title">
                Vamos proteger o que é importante para você
            </h2>

            <p class="section-subtitle">
                Entre em contato com a Coalizão Segurança e descubra
                a solução mais adequada para sua necessidade.
            </p>

        </div>


        <div class="contato-conteudo">


            <!-- INFORMAÇÕES -->

            <div class="contato-informacoes">

                <div class="contato-item">

                    <div class="contato-icon" aria-hidden="true">
                        <span class="icon-telefone"></span>
                    </div>

                    <div>
                        <h3>
                            Telefone
                        </h3>

                        <p>
                            (00) 00000-0000
                        </p>
                    </div>

                </div>


                <div class="contato-item">

                    <div class="contato-icon" aria-hidden="true">
                        <span class="icon-email"></span>
                    </div>

                    <div>
                        <h3>
                            E-mail
                        </h3>

                        <p>
                            contato@coalizacaoseguranca.com.br
                        </p>
                    </div>

                </div>


                <div class="contato-item">

                    <div class="contato-icon" aria-hidden="true">
                        <span class="icon-localizacao"></span>
                    </div>

                    <div>
                        <h3>
                            Atendimento
                        </h3>

                        <p>
                            Atendimento comercial para sua empresa,
                            condomínio ou residência.
                        </p>
                    </div>

                </div>

            </div>


            <!-- FORMULÁRIO -->

            <div class="contato-formulario">

                <form action="php/solicitar_orcamento.php" method="POST">

                    <div class="form-grupo">

                        <label for="nome">
                            Nome
                        </label>

                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            placeholder="Seu nome completo"
                            required
                        >

                    </div>


                    <div class="form-grupo">

                        <label for="email">
                            E-mail
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="seuemail@exemplo.com"
                            required
                        >

                    </div>


                    <div class="form-grupo">

                        <label for="telefone">
                            Telefone
                        </label>

                        <input
                            type="tel"
                            id="telefone"
                            name="telefone"
                            placeholder="(00) 00000-0000"
                            required
                        >

                    </div>


                    <div class="form-grupo">

                        <label for="mensagem">
                            Como podemos ajudar?
                        </label>

                        <textarea
                            id="mensagem"
                            name="mensagem"
                            rows="5"
                            placeholder="Conte um pouco sobre sua necessidade..."
                            required
                        ></textarea>

                    </div>


                    <button type="submit" class="btn">
                        Solicitar Orçamento
                    </button>

                </form>

            </div>


        </div>

    </div>

</section>
