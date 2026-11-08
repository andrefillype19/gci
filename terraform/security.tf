# Security Group da Aplicação (Subnet Pública)
resource "aws_security_group" "app_sg" {
  name        = "gci-app-sg"
  description = "Permite tráfego HTTP, HTTPS e SSH para a aplicação"
  vpc_id      = aws_vpc.gci_vpc.id

  ingress {
    description = "SSH"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "HTTP Ingress"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
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

# Security Group do Banco de Dados (Subnet Privada)
resource "aws_security_group" "db_sg" {
  name        = "gci-db-sg"
  description = "Permite conexões do PostgreSQL vindas apenas do Security Group da Aplicação"
  vpc_id      = aws_vpc.gci_vpc.id

  # Libera a porta 5432 APENAS para quem está associado ao app_sg
  ingress {
    description     = "PostgreSQL a partir da EC2 App"
    from_port       = 5432
    to_port         = 5432
    protocol        = "tcp"
    security_groups = [aws_security_group.app_sg.id]
  }

  # Libera SSH vindo do Bastion (EC2 App pública)
  ingress {
    description     = "SSH vindo da EC2 App (Bastion)"
    from_port       = 22
    to_port         = 22
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