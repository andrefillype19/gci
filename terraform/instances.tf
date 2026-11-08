# IAM Role para o SSM (Caso deseje conectar via AWS Session Manager)
resource "aws_iam_role" "ssm_role" {
  name = "gci-ec2-ssm-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Action    = "sts:AssumeRole"
      Effect    = "Allow"
      Principal = { Service = "ec2.amazonaws.com" }
    }]
  })
}

resource "aws_iam_role_policy_attachment" "ssm_policy" {
  role       = aws_iam_role.ssm_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_instance_profile" "ssm_profile" {
  name = "gci-ssm-instance-profile"
  role = aws_iam_role.ssm_role.name
}

# 1. Instância do Banco de Dados (Subnet Privada - m7i-flex.large)
resource "aws_instance" "postgres_db" {
  ami                  = data.aws_ami.ubuntu.id
  instance_type        = var.db_instance_type
  subnet_id            = aws_subnet.private_subnet.id
  vpc_security_group_ids = [aws_security_group.db_sg.id]
  key_name             = var.key_name
  iam_instance_profile = aws_iam_instance_profile.ssm_profile.name

  root_block_device {
    volume_size = 15
    volume_type = "gp3"
  }

  # Provisionamento automático do PostgreSQL nativo no boot
  user_data = <<-EOF
              #!/bin/bash
              apt update && apt upgrade -y
              apt install postgresql postgresql-contrib -y
              
              # Configura o escuta de rede para a VPC
              sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/g" /etc/postgresql/16/main/postgresql.conf
              echo "host all all 10.0.0.0/16 scram-sha-256" >> /etc/postgresql/16/main/pg_hba.conf
              
              # Criação do banco e credenciais padrão
              sudo -u postgres psql -c "CREATE DATABASE \"gci-test-db\";"
              sudo -u postgres psql -c "ALTER USER postgres WITH PASSWORD 'admin';"
              
              systemctl restart postgresql
              EOF

  tags = {
    Name = "gci-postgres-db"
  }
}

# 2. Instância da Aplicação / k3s (Subnet Pública - t3.small)
resource "aws_instance" "app_server" {
  ami                  = data.aws_ami.ubuntu.id
  instance_type        = var.app_instance_type
  subnet_id            = aws_subnet.public_subnet.id
  vpc_security_group_ids = [aws_security_group.app_sg.id]
  key_name             = var.key_name

  root_block_device {
    volume_size = 20
    volume_type = "gp3"
  }

  tags = {
    Name = "gci-app-server"
  }
}