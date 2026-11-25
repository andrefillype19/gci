# =====================================================
# EXPERIMENTO 2: 2 AGENTS K3S + GERADOR DE CARGA NA VPC
# =====================================================
# Nao altera nenhum recurso existente. A entrada dos agents no cluster e manual
# (SSH, com o token do server): o token nao pode ficar no Terraform nem no user_data.
# Regra do Security Group entre os membros: ver security.tf (app_sg).

variable "agent_instance_type" {
  description = "Tipo das EC2 dos agents k3s"
  type        = string
  default     = "t3.small"
}

variable "loadgen_instance_type" {
  description = "Tipo da EC2 do gerador de carga (k6)"
  type        = string
  default     = "t3.small"
}

variable "k6_version" {
  description = "Versao do k6 instalada no gerador (a mesma do Experimento 1), no formato vX.Y.Z"
  type        = string
  default     = "v2.4.0"
}

variable "deadman_minutes" {
  description = "Minutos apos o boot do gerador em que ele para TODAS as instancias com tag Project=GCI"
  type        = number
  default     = 720
}

locals {
  k3s_agents = toset(["gci-agent-1", "gci-agent-2"])
}

# =====================================================
# AGENTS K3S (mesma AZ e sub-rede publica do server)
# =====================================================

resource "aws_instance" "k3s_agent" {
  for_each = local.k3s_agents

  ami           = data.aws_ssm_parameter.ubuntu_2404_ami.value
  instance_type = var.agent_instance_type

  subnet_id              = aws_subnet.public_subnet.id
  vpc_security_group_ids = [aws_security_group.app_sg.id]

  iam_instance_profile = aws_iam_instance_profile.ssm_profile.name
  key_name             = var.key_name

  root_block_device {
    volume_size = 20
    volume_type = "gp3"
    encrypted   = true
  }

  user_data = file("${path.module}/scripts/k3s-agent/bootstrap.sh")

  # Evita recriar (e perder o no do cluster) quando a AMI do SSM ou o script mudarem.
  lifecycle {
    ignore_changes = [ami, user_data]
  }

  tags = {
    Name    = each.key
    Project = "GCI"
    Service = "Application"
  }
}

# =====================================================
# IAM DO GERADOR (permissoes minimas)
# =====================================================

resource "aws_iam_role" "loadgen_role" {
  name = "gci-loadgen-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"

    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"

        Principal = {
          Service = "ec2.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "loadgen_ssm" {
  role       = aws_iam_role.loadgen_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_role_policy" "loadgen_inline" {
  name = "gci-loadgen-inline"
  role = aws_iam_role.loadgen_role.id

  policy = jsonencode({
    Version = "2012-10-17"

    Statement = [
      {
        Sid    = "LeituraEMetricas"
        Effect = "Allow"

        Action = [
          "ec2:Describe*",
          "cloudwatch:GetMetricStatistics",
          "cloudwatch:GetMetricData",
          "ssm:GetCommandInvocation",
          "ssm:ListCommandInvocations",
          "ssm:DescribeInstanceInformation"
        ]

        Resource = "*"
      },
      {
        # F1 (queda do NFS): so o servidor NFS e so o documento AWS-RunShellScript.
        Sid    = "SsmSomenteNoServidorNfs"
        Effect = "Allow"
        Action = "ssm:SendCommand"

        Resource = [
          "arn:aws:ssm:${var.aws_region}::document/AWS-RunShellScript",
          aws_instance.nfs_server.arn
        ]
      },
      {
        # F1 com EFS: simular a queda removendo/recolocando so a regra 2049 do SG do EFS.
        Sid    = "FalhaEfsSomenteNoSgDoEfs"
        Effect = "Allow"

        Action = [
          "ec2:RevokeSecurityGroupIngress",
          "ec2:AuthorizeSecurityGroupIngress"
        ]

        Resource = aws_security_group.efs_sg.arn
      },
      {
        # Dead-man switch e encerramento da pilha: so instancias com tag Project=GCI.
        Sid      = "PararPilhaPorTag"
        Effect   = "Allow"
        Action   = "ec2:StopInstances"
        Resource = "arn:aws:ec2:${var.aws_region}:${data.aws_caller_identity.current.account_id}:instance/*"

        Condition = {
          StringEquals = {
            "aws:ResourceTag/Project" = "GCI"
          }
        }
      }
    ]
  })
}

resource "aws_iam_instance_profile" "loadgen_profile" {
  name = "gci-loadgen-profile"
  role = aws_iam_role.loadgen_role.name
}

# =====================================================
# GERADOR DE CARGA (k6 dentro da VPC)
# =====================================================

resource "aws_instance" "loadgen" {
  ami           = data.aws_ssm_parameter.ubuntu_2404_ami.value
  instance_type = var.loadgen_instance_type

  subnet_id              = aws_subnet.public_subnet.id
  vpc_security_group_ids = [aws_security_group.app_sg.id]

  iam_instance_profile = aws_iam_instance_profile.loadgen_profile.name
  key_name             = var.key_name

  # Resultados brutos do k6 (--out json): reserve 2 a 3 GB livres.
  root_block_device {
    volume_size = 30
    volume_type = "gp3"
    encrypted   = true
  }

  user_data = templatefile("${path.module}/scripts/loadgen/bootstrap.sh", {
    k6_version      = var.k6_version
    aws_region      = var.aws_region
    deadman_minutes = var.deadman_minutes
  })

  lifecycle {
    ignore_changes = [ami, user_data]
  }

  tags = {
    Name    = "gci-loadgen"
    Project = "GCI"
    Service = "LoadGenerator"
  }
}

# =====================================================
# SAIDAS
# =====================================================

output "app_private_ip" {
  description = "IP privado do server k3s (K3S_URL dos agents e BASE_URL do k6)"
  value       = aws_instance.app_server.private_ip
}

output "agents_private_ips" {
  description = "IPs privados dos agents k3s"
  value       = { for name, inst in aws_instance.k3s_agent : name => inst.private_ip }
}

output "agents_public_ips" {
  description = "IPs publicos dos agents k3s (SSH)"
  value       = { for name, inst in aws_instance.k3s_agent : name => inst.public_ip }
}

output "loadgen_public_ip" {
  description = "IP publico do gerador de carga (SSH)"
  value       = aws_instance.loadgen.public_ip
}

output "loadgen_private_ip" {
  description = "IP privado do gerador de carga"
  value       = aws_instance.loadgen.private_ip
}
