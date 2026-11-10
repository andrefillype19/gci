# =====================================================
# SECURITY GROUP DA APLICAÇÃO
# =====================================================

resource "aws_security_group" "app_sg" {
  name        = "gci-app-sg"
  description = "Security Group da aplicação GCI"
  vpc_id      = aws_vpc.gci_vpc.id

  ingress {
    description = "SSH"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "HTTP"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "HTTPS"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "NAT instance - trafego de saida da subnet privada"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = [aws_subnet.private_subnet.cidr_block]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "gci-app-sg"
  }
}

# =====================================================
# SECURITY GROUP DO POSTGRESQL
# =====================================================

resource "aws_security_group" "db_sg" {
  name        = "gci-db-sg"
  description = "Acesso privado ao PostgreSQL"
  vpc_id      = aws_vpc.gci_vpc.id

  ingress {
    description     = "PostgreSQL somente da aplicacao"
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.app_sg.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "gci-db-sg"
  }
}