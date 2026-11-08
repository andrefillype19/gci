# IAM Role para o AWS Systems Manager (SSM)
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

# 1. Instância do Banco de Dados PostgreSQL 11 (Subnet Privada - m7i-flex.large)
resource "aws_instance" "postgres_db" {
  ami                  = data.aws_ssm_parameter.ubuntu_2404_ami.value
  instance_type        = var.db_instance_type
  subnet_id            = aws_subnet.private_subnet.id
  vpc_security_group_ids = [aws_security_group.db_sg.id]
  key_name             = var.key_name
  iam_instance_profile = aws_iam_instance_profile.ssm_profile.name

  root_block_device {
    volume_size = 15
    volume_type = "gp3"
  }

  user_data = <<-EOF
              #!/bin/bash
              set -e

              # 1. Instalação do PostgreSQL 11 via repositório PGDG
              apt update && apt install -y curl ca-certificates lsb-release gnupg
              curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg
              echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list

              apt update
              apt install -y postgresql-11 postgresql-client-11

              # 2. Configurações de escuta na rede interna
              PG_CONF="/etc/postgresql/11/main/postgresql.conf"
              PG_HBA="/etc/postgresql/11/main/pg_hba.conf"

              sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/g" $PG_CONF
              echo "host all all 10.0.0.0/16 scram-sha-256" >> $PG_HBA
              echo "host all all 10.0.0.0/16 md5" >> $PG_HBA

              systemctl restart postgresql

              # 3. Preparação do diretório de restauração
              mkdir -p /tmp/db-init

              # 4. Injeção dos arquivos lidos da pasta ../database/
              cat << 'EOSCRIPT1' > /tmp/db-init/01-create-role.sh
              ${file("${path.module}/../database/init/01-create-role.sh")}
              EOSCRIPT1

              cat << 'EOSQL' > /tmp/db-init/gci.sql
              ${file("${path.module}/../database/gci.sql")}
              EOSQL

              cat << 'EOSCRIPT2' > /tmp/db-init/02-restore.sh
              ${file("${path.module}/../database/init/02-restore.sh")}
              EOSCRIPT2

              # 5. Execução do Restore
              chmod -R 777 /tmp/db-init
              cd /tmp/db-init
              sudo -u postgres ./01-create-role.sh
              sudo -u postgres ./02-restore.sh

              rm -rf /tmp/db-init
              systemctl restart postgresql
              EOF

  tags = {
    Name = "gci-postgres-db"
  }
}

# 2. Instância da Aplicação / k3s (Subnet Pública - t3.small)
resource "aws_instance" "app_server" {
  ami                  = data.aws_ssm_parameter.ubuntu_2404_ami.value
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