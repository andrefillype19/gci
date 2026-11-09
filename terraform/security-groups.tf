# 1. Definir o Security Group do Banco de Dados (caso ainda não esteja explicitado)
resource "aws_security_group" "db_sg" {
  name        = "gci-db-sg"
  description = "Security group para a instancia do PostgreSQL"
  vpc_id      = aws_vpc.main.id # Ajuste para a sua variável de VPC

  tags = {
    Name = "gci-db-sg"
  }
}

# 2. Inbound Rule: Permitir tráfego na porta 5432 vindo EXCLUSIVAMENTE do Security Group do K3s
resource "aws_vpc_security_group_ingress_rule" "allow_postgres_from_k3s" {
  security_group_id            = aws_security_group.db_sg.id
  description                  = "Permite conexao PostgreSQL (5432) vinda da instancia k3s"
  from_port                    = 5432
  to_port                      = 5432
  ip_protocol                  = "tcp"
  referenced_security_group_id = aws_security_group.app_sg.id
}